<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\SwInclude;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('framework')]
final class IconTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): SwInclude
    {
        /** @var AbstractExpression $expr */
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $icon = $expr->getAttribute('value');

        $expr->setAttribute('value', '@Storefront/storefront/utilities/icon.html.twig');

        $stream = $this->parser->getStream();

        $variables = new ArrayExpression([], $token->getLine());

        if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->next();

        $variables->addElement(
            new ConstantExpression($icon, $token->getLine()),
            new ConstantExpression('name', $token->getLine())
        );

        return new SwInclude($expr, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_icon';
    }
}
