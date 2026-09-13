<?php

declare(strict_types=1);

namespace Chandler\MVC;

use Chandler\MVC\Latte\ChandlerExtension;
use Latte\Engine as TemplatingEngine;
use Nette\SmartObject;

abstract class SimplePresenter implements IPresenter
{
    use SmartObject;
    public const REDIRECT_PERMAMENT = 1;
    public const REDIRECT_TEMPORARY = 2;
    public const REDIRECT_PERMAMENT_PRESISTENT = 8;
    public const REDIRECT_TEMPORARY_PRESISTENT = 7;

    protected $mmReader;
    protected $template;
    protected $errorTemplate = null;

    public function __construct()
    {
        $this->template = (object) [];
    }

    public function getTemplatingEngine(): TemplatingEngine
    {
        $latte = new TemplatingEngine();

        $latte->setTempDirectory(CHANDLER_ROOT . "/tmp/cache/templates");
        $latte->addExtension(new \Latte\Bridges\Tracy\TracyExtension());
        $latte->addExtension(new \Latte\Essential\RawPhpExtension());

        $latte->addExtension(new ChandlerExtension(static::class));

        return $latte;
    }

    protected function throwError(int $code = 400, string $desc = "Bad Request", string $message = ""): void
    {
        if (!is_null($this->errorTemplate)) {
            header("HTTP/1.0 $code $desc");

            $ext = explode("\\", get_class($this))[0];
            $path = \Chandler\MVC\Routing\Router::getExtensionPath($ext) . "/Web/Presenters/templates/" . $this->errorTemplate . ".latte";

            $latte = $this->getTemplatingEngine();
            $latte->render($path, array_merge_recursive([
                "code" => $code,
                "desc" => $desc,
                "msg" => $message,
            ], $this->getTemplateScope()));
            exit;
        } else {
            chandler_http_panic($code, $desc, $message);
        }
    }

    protected function assertNoCSRF(): void
    {
        if (!$GLOBALS["csrfCheck"]) {
            $this->throwError(400, "Bad Request", "CSRF token is missing or invalid.");
        }
    }

    protected function terminate(): void
    {
        throw new Exceptions\InterruptedException();
    }

    protected function notFound(): void
    {
        $this->throwError(
            404,
            "Not Found",
            "The resource you are looking for has been deleted, had its name changed or doesn't exist."
        );
    }

    protected function getCaller(): string
    {
        return $GLOBALS["parentModule"] ?? "libchandler:absolute.0";
    }

    protected function redirect(string $location, int $code = 2): void
    {
        if (!$this->isSafeRedirect($location)) {
            $this->throwError(400, "Bad Request", "Invalid redirect target.");
        }

        $code = 300 + $code;
        if (($code <=> 300) !== 0 && $code > 399) {
            return;
        }

        header("HTTP/1.1 $code");
        header("Location: $location");
        exit;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        // localhost:8080 -> localhost
        if (str_contains($host, ":")) {
            $host = explode(":", $host)[0];
        }

        return $host;
    }

    private function isSafeRedirect(string $location): bool
    {
        // Check relative paths. Disallow the use of workarounds.
        if (
            str_starts_with($location, "/")
            && !str_starts_with($location, "//")
            && !str_contains($location, "\\")
        ) {
            return true;
        }

        $scheme = strtolower((string) parse_url($location, PHP_URL_SCHEME));
        if (!in_array($scheme, ["http", "https"], true)) {
            return false;
        }

        $host = parse_url($location, PHP_URL_HOST);
        if (!is_string($host) || $host === "") {
            return false;
        }
        $host = $this->normalizeHost($host);

        // Whitelist of domains to which redirects are allowed
        $allowedHosts = [];

        // The request's own host is always a valid redirect target, so
        // redirects work out of the box even without any configuration.
        $currentHost = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "";
        if ($currentHost !== "") {
            $allowedHosts[] = $this->normalizeHost($currentHost);
        }

        // Trusted domains (specified in the config)
        foreach (CHANDLER_ROOT_CONF["security"]["trustedRedirectHosts"] ?? [] as $trusted) {
            $allowedHosts[] = $this->normalizeHost((string) $trusted);
        }

        return in_array($host, $allowedHosts, true);
    }

    protected function pass(string $to, ...$args): void
    {
        $args = array_merge([$to], $args);
        $router = \Chandler\MVC\Routing\Router::i();
        $__out = $router->execute($router->reverse(...$args), "libchandler:absolute.0");
        exit($__out);
    }

    protected function sendmail(string $to, string $template, array $params): void
    {
        $emailDir = pathinfo($template, PATHINFO_DIRNAME);
        $template .= ".eml.latte";

        $renderedHTML = (new TemplatingEngine())->renderToString($template, $params);
        $document = new \DOMDocument();
        $document->loadHTML($renderedHTML, LIBXML_NOEMPTYTAG);
        $querySel = new \DOMXPath($document);

        $subject = $querySel->query("//title/text()")->item(0)->data;

        foreach ($querySel->query("//link[@rel='stylesheet']") as $link) {
            $style = $document->createElement("style");
            $style->setAttribute("id", uniqid("mail", true));
            $style->appendChild(new \DOMText(file_get_contents("$emailDir/assets/css/" . $link->getAttribute("href"))));

            $link->parentNode->appendChild($style);
            $link->parentNode->removeChild($link);
        }

        foreach ($querySel->query("//img") as $image) {
            $imagePath = "$emailDir/assets/res/" . $image->getAttribute("src");
            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $contents = base64_encode(file_get_contents($imagePath));

            $image->setAttribute("src", "data:image/$type;base64,$contents");
        }

        \Chandler\Email\Email::send($to, $subject, $document->saveHTML());
    }

    protected function queryParam(string $index): ?string
    {
        return $_GET[$index] ?? null;
    }

    protected function postParam(string $index, bool $csrfCheck = true): ?string
    {
        if ($csrfCheck) {
            $this->assertNoCSRF();
        }

        return $_POST[$index] ?? null;
    }

    protected function requestParam(string $index): ?string
    {
        return $_REQUEST[$index] ?? null;
    }

    protected function jsonParam(string $index): ?string
    {
        if (!isset($GLOBALS["jsonInputCache"])) {
            $GLOBALS["jsonInputCache"] = json_decode($this->queryParam("js") ?? file_get_contents("php://input"), true);
        }

        return $GLOBALS["jsonInputCache"][$index] ?? null;
    }

    protected function findParam(string $index, array $searchIn = ["json", "post", "query"]): ?string
    {
        $res = null;
        foreach ($searchIn as $search) {
            if ($search === "json") {
                $res = $this->jsonParam($index) ?? $res;
            } elseif ($search === "post") {
                $res = $this->postParam($index, false) ?? $res;
            } elseif ($search === "query") {
                $res = $this->queryParam($index) ?? $res;
            }
        }

        return $res;
    }

    protected function checkbox(string $name): bool
    {
        return ($this->postParam($name) ?? "off") === "on";
    }

    public function getTemplateScope(): array
    {
        return (array) $this->template;
    }

    public function onStartup(): void
    {
        date_default_timezone_set("UTC");
    }

    public function onBeforeRender(): void
    {
        $this->template->csrfToken = $GLOBALS["csrfToken"];
    }

    public function onAfterRender(): void {}

    public function onStop(): void {}

    public function onDestruction(): void {}
}
