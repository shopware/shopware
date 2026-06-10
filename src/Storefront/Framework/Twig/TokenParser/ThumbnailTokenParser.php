<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\FinderTemplateExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\IncludeNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('framework')]
final class ThumbnailTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): IncludeNode
    {
        $expr = $this->parser->parseExpression();
        $stream = $this->parser->getStream();

        $className = $expr->getAttribute('value');
        $expr->setAttribute('value', '@Storefront/storefront/utilities/thumbnail.html.twig');

        $variables = new ArrayExpression([], $token->getLine());
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->parseExpression();
        }

        $stream->next();

        $variables->addElement(
            new ConstantExpression($className, $token->getLine()),
            new ConstantExpression('name', $token->getLine())
        );

        $resolved = new FinderTemplateExpression($expr, $token->getLine());

        return new IncludeNode($resolved, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_thumbnails';
    }
}
