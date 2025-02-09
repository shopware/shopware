<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\AssignTemplateVariable;
use Twig\Node\Expression\Variable\TemplateVariable;
use Twig\Node\ImportNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 *
 * @see \Twig\TokenParser\FromTokenParser
 */
#[Package('framework')]
final class FromTokenParser extends AbstractTokenParser
{
    public function __construct(private readonly TemplateFinderInterface $templateFinder)
    {
    }

    public function parse(Token $token): Node
    {
        $macro = $this->parser->getExpressionParser()->parseExpression();

        // sw-fix-start
        if ($macro instanceof ConstantExpression) {
            $macro->setAttribute('value', $this->templateFinder->find($macro->getAttribute('value')));
        }
        // sw-fix-end

        $stream = $this->parser->getStream();
        $stream->expect(Token::NAME_TYPE, 'import');

        $targets = [];
        while (true) {
            $name = $stream->expect(Token::NAME_TYPE)->getValue();

            if ($stream->nextIf('as')) {
                $alias = new AssignContextVariable($stream->expect(Token::NAME_TYPE)->getValue(), $token->getLine());
            } else {
                $alias = new AssignContextVariable($name, $token->getLine());
            }

            $targets[$name] = $alias;

            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $internalRef = new AssignTemplateVariable(new TemplateVariable(null, $token->getLine()), $this->parser->isMainScope());
        $node = new ImportNode($macro, $internalRef, $token->getLine());

        foreach ($targets as $name => $alias) {
            $this->parser->addImportedSymbol('function', $alias->getAttribute('name'), 'macro_' . $name, $internalRef);
        }

        return $node;
    }

    public function getTag(): string
    {
        return 'sw_from';
    }
}
