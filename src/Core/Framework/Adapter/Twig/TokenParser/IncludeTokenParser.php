<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\SwInclude;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('core')]
final class IncludeTokenParser extends AbstractTokenParser
{
    /**
     * @var Parser
     */
    protected $parser;

    public function __construct(private readonly TemplateFinder $finder)
    {
    }

    /**
     * @return Node
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        [$variables, $only, $ignoreMissing] = $this->parseArguments();

        //resolves parent template
        if ($expr->hasAttribute('value')) {
            //set pointer to next value (contains the template file name)
            $parent = $this->finder->find($expr->getAttribute('value'), $ignoreMissing);

            $expr->setAttribute('value', $parent);

            return new IncludeNode($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
        }

        return new SwInclude($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    public function getTag(): string
    {
        return 'sw_include';
    }

    private function parseArguments(): array
    {
        $stream = $this->parser->getStream();

        $ignoreMissing = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'ignore')) {
            $stream->expect(Token::NAME_TYPE, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'only')) {
            $only = true;
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return [$variables, $only, $ignoreMissing];
    }
}
