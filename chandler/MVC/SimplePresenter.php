<?php declare(strict_types=1);
namespace Chandler\MVC;
use Chandler\Session\Session;
use Latte\Engine as TemplatingEngine;
use Nette\SmartObject;

abstract class SimplePresenter implements IPresenter
{
    const REDIRECT_PERMAMENT = 1;
    const REDIRECT_TEMPORARY = 2;
    const REDIRECT_PERMAMENT_PRESISTENT = 8;
    const REDIRECT_TEMPORARY_PRESISTENT = 7;
    
    protected $mmReader;
    protected $template;
    protected $errorTemplate = NULL;
    
    function __construct()
    {
        $this->template = (object) [];
    }
    
    function getTemplatingEngine(): TemplatingEngine
    {
        $latte  = new TemplatingEngine;
        $macros = new  \Latte\Macros\MacroSet($latte->getCompiler());
        $latte->setTempDirectory(CHANDLER_ROOT . "/tmp/cache/templates");
        
        $macros->addMacro("css", '
            $domain   = "' . explode("\\", static::class)[0] . '";
            $file     = (%node.array)[0];
            $realpath = CHANDLER_EXTENSIONS_ENABLED . "/$domain/Web/static/$file";
            if(file_exists($realpath)) {
                $hash = "sha384-" . base64_encode(hash_file("sha384", $realpath, true));
                $mod  = bin2hex(filemtime($realpath));
                echo "<link rel=\'stylesheet\' href=\'/assets/packages/static/$domain/$file?mod=$mod\' integrity=\'$hash\' />";
            } else {
                echo "<!-- ERR: $file does not exist. Not including. -->";
            }
        ');
        $macros->addMacro("script", '
            $domain   = "' . explode("\\", static::class)[0] . '";
            $file     = (%node.array)[0];
            $realpath = CHANDLER_EXTENSIONS_ENABLED . "/$domain/Web/static/$file";
            if(file_exists($realpath)) {
                $hash = "sha384-" . base64_encode(hash_file("sha384", $realpath, true));
                $mod  = bin2hex(filemtime($realpath));
                echo "<script src=\'/assets/packages/static/$domain/$file?mod=$mod\' integrity=\'$hash\'></script>";
            } else {
                echo "<!-- ERR: $file does not exist. Not including. -->";
            }
        ');
        $macros->addMacro("presenter", '
            $input  = (%node.array);
            
            echo "<!-- Trying to invoke $input[0] through router from ' . static::class . ' -->";
            
            $router = \Chandler\MVC\Routing\Router::i();
            $__out  = $router->execute($router->reverse(...$input), "' . static::class . '");
            echo $__out;
            
            echo "<!-- Inclusion complete -->";
            '
        );
        
        return $latte;
    }
    
    protected function throwError(int $code = 400, string $desc = "Bad Request", string $message = ""): void
    {
        if(!is_null($this->errorTemplate)) {
            header("HTTP/1.0 $code $desc");
            
            $ext   = explode("\\", get_class($this))[0];
            $path  = CHANDLER_EXTENSIONS_ENABLED . "/$ext/Web/Presenters/templates/" . $this->errorTemplate . ".xml";
            
            $latte = new TemplatingEngine;
            $latte->setTempDirectory(CHANDLER_ROOT . "/tmp/cache/templates");
            $latte->render($path, array_merge_recursive([
                "code" => $code,
                "desc" => $desc,
                "msg"  => $message,
            ], $this->getTemplateScope()));
            exit;
        } else {
            chandler_http_panic($code, $desc, $message);
        }
    }
    
    protected function assertNoCSRF(): void
    {
        if(!$GLOBALS["csrfCheck"])
            $this->throwError(400, "Bad Request", "CSRF token is missing or invalid.");
    }
    
    protected function terminate(): void
    {
        throw new Exceptions\InterruptedException;
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
        $code = 300 + $code;
        if(($code <=> 300) !== 0 && $code > 399) return;
        
        header("HTTP/1.1 $code");
        header("Location: $location");
        exit;
    }
    
    protected function pass(string $to, ...$args): void
    {
        $args   = array_merge([$to], $args);
        $router = \Chandler\MVC\Routing\Router::i();
        $__out  = $router->execute($router->reverse(...$args), "libchandler:absolute.0");
        exit($__out);
    }
    
    protected function sendmail(string $to, string $template, array $params): void
    {
        $emailDir  = pathinfo($template, PATHINFO_DIRNAME);
        $template .= ".eml.latte";
        
        $renderedHTML = (new TemplatingEngine)->renderToString($template, $params);
        $document     = new \DOMDocument();
        $document->loadHTML($renderedHTML, LIBXML_NOEMPTYTAG);
        $querySel     = new \DOMXPath($document);
        
        $subject = $querySel->query("//title/text()")->item(0)->data;
        
        foreach($querySel->query("//link[@rel='stylesheet']") as $link) {
            $style = $document->createElement("style");
            $style->setAttribute("id", uniqid("mail", true));
            $style->appendChild(new \DOMText(file_get_contents("$emailDir/assets/css/" . $link->getAttribute("href"))));
            
            $link->parentNode->appendChild($style);
            $link->parentNode->removeChild($link);
        }
        
        foreach($querySel->query("//img") as $image) {
            $imagePath = "$emailDir/assets/res/" . $image->getAttribute("src");
            $type      = pathinfo($imagePath, PATHINFO_EXTENSION);
            $contents  = base64_encode(file_get_contents($imagePath));

            $image->setAttribute("src", "data:image/$type;base64,$contents");
        }
        
        \Chandler\Email\Email::send($to, $subject, $document->saveHTML());
    }
    
    protected function queryParam(string $index): ?string
    {
        return $_GET[$index] ?? NULL;
    }
    
    protected function postParam(string $index): ?string
    {
        $this->assertNoCSRF();
        
        return $_POST[$index] ?? NULL;
    }
    
    protected function checkbox(string $name): bool
    {
        return ($this->postParam($name) ?? "off") === "on";
    }
    
    function getTemplateScope(): array
    {
        return (array) $this->template;
    }
    
    function onStartup(): void
    {
        date_default_timezone_set("UTC");
    }
    
    function onBeforeRender(): void
    {
        $this->template->csrfToken = $GLOBALS["csrfToken"];
    }
    
    function onAfterRender(): void
    {}
    
    function onStop(): void
    {}
    
    function onDestruction(): void
    {}
    
    use SmartObject;
}
