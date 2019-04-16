<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\TokenParser;

use Shopware\Core\Framework\Twig\Node\SwInclude;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Twig\Node\IncludeNode;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class IncludeTokenParser extends AbstractTokenParser
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(TemplateFinder $finder)
    {
        $this->finder = $finder;
    }

    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        [$variables, $only, $ignoreMissing] = $this->parseArguments();

        //resolves parent template
        if ($expr->hasAttribute('value')) {
            $template = $this->finder->getTemplateName($expr->getAttribute('value'));

            //set pointer to next value (contains the template file name)
            $parent = $this->finder->find($template, $ignoreMissing);

            $expr->setAttribute('value', $parent);
        } elseif ($expr->hasAttribute('name')) {
            return new SwInclude($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
        }

        return new IncludeNode($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
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
