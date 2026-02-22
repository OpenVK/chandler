<?php

declare(strict_types=1);

namespace Chandler\MVC\Latte;

class PresenterNode extends \Latte\Compiler\Nodes\StatementNode
{
    public $input;

    public static function create(\Latte\Compiler\Tag $tag): self
    {
        $tag->expectArguments();
        $node = new self();
        $node->input = $tag->parser->parseArguments();
        return $node;
    }

    public function print(\Latte\Compiler\PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                %line
                $__input  = %node;

                echo "<!-- Trying to invoke $__input[0] through router from " . $this->global->chandlerPresenter . " -->";
                
                $__router = \Chandler\MVC\Routing\Router::i();
                $__out  = $__router->execute($__router->reverse(...$__input), $this->global->chandlerPresenter);
                echo $__out;
                
                echo "<!-- Inclusion complete -->";
                XX,
            $this->position,
            $this->input
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->input;
    }
}
