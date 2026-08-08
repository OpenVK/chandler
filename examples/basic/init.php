<?php

declare(strict_types=1);

/*
 * init.php — Extension init script
 *
 * This file is require'd by ExtensionManager after constants are
 * defined but before routes are loaded. If it returns a callable,
 * that callable is invoked immediately.
 *
 * Use this hook for:
 *   - One-time checks (PHP version, required extensions)
 *   - Setting locale / timezone
 *   - Defining global helper functions
 *
 * In this minimal example there is nothing to do — the closure
 * is a no-op. Kept here to document the pattern.
 */

return function (): void {};
