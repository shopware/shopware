<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\EmbedNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\Token;

/**
 * @see \Twig\TokenParser\EmbedTokenParser
 */
#[Package('core')]
final class EmbedTokenParser extends \Twig\TokenParser\IncludeTokenParser
{
    public function __construct(private readonly TemplateFinderInterface $templateFinder)
    {
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();

        $parent = $this->parser->getExpressionParser()->parseExpression();

        // sw-fix-start
        $templateName = $parent->getAttribute('value');
        $resolvedTemplateName = $this->templateFinder->find($templateName);

        $parent->setAttribute('value', $resolvedTemplateName);
        // sw-fix-end

        [$variables, $only, $ignoreMissing] = $this->parseArguments();

        $parentToken = $fakeParentToken = new Token(Token::STRING_TYPE, '__parent__', $token->getLine());
        if ($parent instanceof ConstantExpression) {
            $parentToken = new Token(Token::STRING_TYPE, $parent->getAttribute('value'), $token->getLine());
        } elseif ($parent instanceof NameExpression) {
            $parentToken = new Token(Token::NAME_TYPE, $parent->getAttribute('name'), $token->getLine());
        }

        // inject a fake parent to make the parent() function work
        $stream->injectTokens([
            new Token(Token::BLOCK_START_TYPE, '', $token->getLine()),
            new Token(Token::NAME_TYPE, 'extends', $token->getLine()),
            $parentToken,
            new Token(Token::BLOCK_END_TYPE, '', $token->getLine()),
        ]);

        $module = $this->parser->parse($stream, $this->decideBlockEnd(...), true);

        // override the parent with the correct one
        if ($fakeParentToken === $parentToken) {
            $module->setNode('parent', $parent);
        }

        $this->parser->embedTemplate($module);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new EmbedNode((string) $module->getTemplateName(), $module->getAttribute('index'), $variables, $only, $ignoreMissing, $token->getLine());
    }

    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('end_sw_embed');
    }

    public function getTag(): string
    {
        return 'sw_embed';
    }
}
