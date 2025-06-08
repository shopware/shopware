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
        /** @var AbstractExpression $iconExpr */
        $iconExpr = $this->parser->getExpressionParser()->parseExpression();

        $expr = new ConstantExpression('@Storefront/storefront/utilities/icon.html.twig', $token->getLine());

        $stream = $this->parser->getStream();

        if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $variables = new ArrayExpression([], $token->getLine());
        }

        $stream->next();

        $variables->addElement(
            $iconExpr,
            new ConstantExpression('name', $token->getLine())
        );

        return new SwInclude($expr, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_icon';
    }
}
