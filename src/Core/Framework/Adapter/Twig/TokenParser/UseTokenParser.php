<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 *
 * @see \Twig\TokenParser\UseTokenParser
 */
#[Package('framework')]
final class UseTokenParser extends AbstractTokenParser
{
    public function __construct(private readonly TemplateFinderInterface $templateFinder)
    {
    }

    public function parse(Token $token): Node
    {
        $template = $this->parser->getExpressionParser()->parseExpression();
        $stream = $this->parser->getStream();

        if (!$template instanceof ConstantExpression) {
            throw AdapterException::swUseSyntaxError($token->getLine(), $stream->getSourceContext());
        }

        // sw-fix-start
        $templateName = $template->getAttribute('value');
        $resolvedTemplateName = $this->templateFinder->find($templateName);

        $template->setAttribute('value', $resolvedTemplateName);
        // sw-fix-end

        $targets = [];
        if ($stream->nextIf('with')) {
            while (true) {
                $name = $stream->expect(Token::NAME_TYPE)->getValue();

                $alias = $name;
                if ($stream->nextIf('as')) {
                    $alias = $stream->expect(Token::NAME_TYPE)->getValue();
                }

                $targets[$name] = new ConstantExpression($alias, -1);

                if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                    break;
                }
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $this->parser->addTrait(new Nodes(['template' => $template, 'targets' => new Nodes($targets)]));

        return new EmptyNode($token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_use';
    }
}
