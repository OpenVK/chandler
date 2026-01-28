<?php

declare(strict_types=1);

namespace Chandler\MVC\Latte;

class ScriptNode extends \Latte\Compiler\Nodes\StatementNode
{
    public $file;

    public static function create(\Latte\Compiler\Tag $tag): self
    {
        $tag->expectArguments();
        $node = new self();
        $node->file = $tag->parser->parseUnquotedStringOrExpression();
        return $node;
    }

    public function print(\Latte\Compiler\PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                %line
                $__domain   = $this->global->chandlerDomain;
                $__file     = %node;
                $__realpath = CHANDLER_EXTENSIONS_ENABLED . "/$__domain/Web/static/$__file";

                if (file_exists($__realpath)) {
                    $__hash = "sha384-" . base64_encode(hash_file("sha384", $__realpath, true));
                    $__mod  = base_convert((string) filemtime($__realpath), 10, 32);
                    echo "<script src='/assets/packages/static/$__domain/$__file?mod=$__mod' integrity='$__hash' crossorigin='anonymous' ></script>";
                } else {
                    echo "<!-- ERR: $__file does not exist. Not including. -->";
                }
                XX,
            $this->position,
            $this->file
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->file;
    }
}
