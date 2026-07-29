#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Structural migration: upgrades an instance from the old architecture
 * (commitcaptcha as a separate extension, chandler.yml with extensions:
 * section) to the new one (captcha built into Chandler, clean config).
 *
 * Usage: php bin/upgrade-structure.php [--dry-run]
 *
 * What it does:
 *   1. Adds chandler.captcha.enable to openvk.yml if missing
 *   2. Removes commitcaptcha from extensions/enabled and extensions/available
 *   3. Removes the deprecated extensions: section from the config
 *   4. Regenerates the Composer autoloader
 */

use Symfony\Component\Yaml\Yaml;

$dryRun = in_array("--dry-run", $argv ?? [], true);

function info(string $msg): void
{
    echo "  \033[36m→\033[0m $msg\n";
}

function ok(string $msg): void
{
    echo "  \033[32m✓\033[0m $msg\n";
}

function warn(string $msg): void
{
    echo "  \033[33m⚠\033[0m $msg\n";
}

function error(string $msg): void
{
    echo "  \033[31m✗\033[0m $msg\n";
}

function run(string $cmd, ?string $cwd = null): ?string
{
    $descriptors = [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]];
    $proc        = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (!$proc) {
        return null;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        return null;
    }
    return $stdout !== false ? $stdout : null;
}

// ─── Detect project root ────────────────────────────────────────────

$candidates = [
    getcwd(),
    dirname(__DIR__),
    dirname(__DIR__) . "/openvk",
];
$projectRoot = null;
foreach ($candidates as $dir) {
    if (file_exists("$dir/openvk.yml") || file_exists("$dir/chandler.yml")) {
        $projectRoot = $dir;
        break;
    }
    if (file_exists("$dir/bootstrap.php")) {
        $projectRoot = $dir;
        break;
    }
}

if (!$projectRoot) {
    error("Cannot detect project root. Run this script from the project directory.");
    exit(1);
}

echo "\033[1mChandler — Structural Migration\033[0m\n";
echo "  Project root: $projectRoot\n";
if ($dryRun) {
    echo "  \033[33mDRY RUN — no changes will be made\033[0m\n";
}
echo "\n";

// ─── 1. Determine which config file is in use ───────────────────────

$mainConfig  = null;
$configFiles = [];

if (file_exists("$projectRoot/openvk.yml")) {
    $configPath = "$projectRoot/openvk.yml";
    $chandlerSectionInOpenvk = true;
} elseif (file_exists("$projectRoot/chandler.yml")) {
    $configPath = "$projectRoot/chandler.yml";
    $chandlerSectionInOpenvk = false;
} else {
    error("No openvk.yml or chandler.yml found.");
    exit(1);
}

$configDir = dirname($configPath);

// ─── 2. Migrate config ──────────────────────────────────────────────

info("Checking configuration...");

if (!function_exists("yaml_parse_file")) {
    require "$projectRoot/vendor/autoload.php";
}

$config   = function_exists("yaml_parse_file") ? yaml_parse_file($configPath) : Yaml::parseFile($configPath);
$chandler = $config["chandler"] ?? [];
$needsSave = false;

// Add the captcha section if it is missing entirely
if (!isset($chandler["captcha"])) {
    info("Adding chandler.captcha.enable: true to config...");
    $chandler["captcha"] = ["enable" => true];
    $needsSave           = true;
} elseif (!isset($chandler["captcha"]["enable"])) {
    info("Adding enable: true to chandler.captcha...");
    $chandler["captcha"]["enable"] = true;
    $needsSave                     = true;
}

// Remove the now-unnecessary extensions section
if (isset($chandler["extensions"])) {
    info("Removing deprecated chandler.extensions section...");
    unset($chandler["extensions"]);
    $needsSave = true;
}

if ($needsSave) {
    $config["chandler"] = $chandler;
    if (!$dryRun) {
        $yaml = "";
        foreach ($config as $key => $value) {
            $yaml .= "$key:\n";
            $yaml .= preg_replace("/^/m", "    ", Yaml::dump($value, 4)) . "\n";
        }
        file_put_contents($configPath, $yaml);
    }
    ok("Configuration updated: captcha added, extensions removed.");
} else {
    ok("Configuration already up-to-date.");
}

// ─── 3. Remove commitcaptcha from extensions/ ───────────────────────

info("Checking for commitcaptcha remnants...");

$extensionDirs = [
    "$projectRoot/extensions/enabled/commitcaptcha",
    "$projectRoot/extensions/available/commitcaptcha",
];

foreach ($extensionDirs as $extPath) {
    if (file_exists($extPath) || is_link($extPath)) {
        info("Removing $extPath...");
        if (!$dryRun) {
            if (is_link($extPath) || is_file($extPath)) {
                unlink($extPath);
            } elseif (is_dir($extPath)) {
                $it = new RecursiveDirectoryIterator($extPath, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($extPath);
            }
        }
        ok("Removed $extPath.");
    }
}

// ─── 4. Verify bootstrap.php ────────────────────────────────────────

$bootstrapPath = "$projectRoot/bootstrap.php";
if (file_exists($bootstrapPath)) {
    $contents = file_get_contents($bootstrapPath);
    if (str_contains($contents, "registerBuiltin")) {
        ok("bootstrap.php already uses registerBuiltin — good.");
    } else {
        warn("bootstrap.php does not seem to register OpenVK as builtin. Manual review needed.");
    }
} else {
    warn("bootstrap.php not found — skipping.");
}

// ─── 5. Validate post-migration state ───────────────────────────────

info("Validating structure...");

$errors = 0;

// Chandler must be reachable
$chandlerDirs = [
    "$projectRoot/vendor/openvk/chandler",
    "$projectRoot/../chandler/chandler",
];
$chandlerFound = false;
foreach ($chandlerDirs as $d) {
    if (is_dir($d)) {
        $chandlerFound = true;
        break;
    }
}
if (!$chandlerFound) {
    warn("Chandler not found in expected locations (vendor/openvk/chandler or ../chandler). Run composer install.");
    $errors++;
}

// Verify commitcaptcha is actually gone
if (is_dir("$projectRoot/extensions/available/commitcaptcha") || is_link("$projectRoot/extensions/enabled/commitcaptcha")) {
    warn("commitcaptcha is still present in extensions/. Manual cleanup may be needed.");
    $errors++;
}

// Verify the captcha section made it into the config
$config   = function_exists("yaml_parse_file") ? yaml_parse_file($configPath) : Yaml::parseFile($configPath);
$chandler = $config["chandler"] ?? [];
if (!isset($chandler["captcha"]["enable"])) {
    warn("captcha section not found in config after migration.");
    $errors++;
}

// ─── 6. Regenerate autoloader ───────────────────────────────────────

if (!$dryRun && file_exists("$projectRoot/composer.json")) {
    info("Regenerating autoloader...");
    $result = run("composer dump-autoload 2>&1", $projectRoot);
    if ($result === null) {
        warn("composer dump-autoload failed. Run it manually.");
    } else {
        ok("Autoloader regenerated.");
    }
}

// ─── Summary ────────────────────────────────────────────────────────

echo "\n";
if ($errors === 0) {
    ok("Migration complete.");
} else {
    warn("Migration finished with $errors warning(s). Review the messages above.");
}
