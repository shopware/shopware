<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\TokenParser;

use Shopware\Core\Framework\Twig\Node\SwInclude;
use Shopware\Core\Framework\Twig\TemplateFinder;

final class IncludeTokenParser extends \Twig_TokenParser
{
    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(TemplateFinder $finder)
    {
        $this->finder = $finder;
    }

    public function parse(\Twig_Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        [$variables, $only, $ignoreMissing] = $this->parseArguments();

        //resolves parent template
        if ($expr->hasAttribute('value')) {
            $parent = $this->finder->find(
            //set pointer to next value (contains the template file name)
                $this->finder->getTemplateName($expr->getAttribute('value')),
                true,
                $ignoreMissing
            );

            $expr->setAttribute('value', $parent);
        } elseif ($expr->hasAttribute('name')) {
            return new SwInclude($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
        }

        return new \Twig_Node_Include($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    public function getTag(): string
    {
        return 'sw_include';
    }

    protected function parseArguments(): array
    {
        $stream = $this->parser->getStream();

        $ignoreMissing = false;
        if ($stream->nextIf(\Twig_Token::NAME_TYPE, 'ignore')) {
            $stream->expect(\Twig_Token::NAME_TYPE, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(\Twig_Token::NAME_TYPE, 'with')) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(\Twig_Token::NAME_TYPE, 'only')) {
            $only = true;
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return [$variables, $only, $ignoreMissing];
    }
}
