<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\FinderTemplateExpression;
use Shopware\Core\Framework\Adapter\Twig\Node\SwInclude;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\IncludeNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
final class IconTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): IncludeNode
    {
        /** @var AbstractExpression $iconExpr */
        $iconExpr = $this->parser->parseExpression();

        $expr = new ConstantExpression('@Storefront/storefront/utilities/icon.html.twig', $token->getLine());

        $stream = $this->parser->getStream();

        if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->parseExpression();
        } else {
            $variables = new ArrayExpression([], $token->getLine());
        }

        $stream->next();

        $variables->addElement(
            $iconExpr,
            new ConstantExpression('name', $token->getLine())
        );

        if (!Feature::isActive('v6.8.0.0')) {
            return new SwInclude($expr, $variables, false, false, $token->getLine());
        }

        $resolved = new FinderTemplateExpression($expr, $token->getLine());

        return new IncludeNode($resolved, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_icon';
    }
}
