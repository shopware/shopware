<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\TokenParser;

use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\IncludeNode;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class ThumbnailTokenParser extends AbstractTokenParser
{
    /**
     * @var Parser
     */
    protected $parser;

    public function parse(Token $token): IncludeNode
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();
        $stream = $this->parser->getStream();

        $className = $expr->getAttribute('value');
        $expr->setAttribute('value', '@Storefront/utilities/thumbnail.html.twig');

        $variables = new ArrayExpression([], $token->getLine());
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->next();

        $variables->addElement(
            new ConstantExpression($className, $token->getLine()),
            new ConstantExpression('name', $token->getLine())
        );

        return new IncludeNode($expr, $variables, false, false, $token->getLine(), $this->getTag());
    }

    public function getTag(): string
    {
        return 'sw_thumbnails';
    }
}
