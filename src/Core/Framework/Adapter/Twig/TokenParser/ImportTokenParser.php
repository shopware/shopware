<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\ImportNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @see \Twig\TokenParser\ImportTokenParser
 */
#[Package('core')]
final class ImportTokenParser extends AbstractTokenParser
{
    public function __construct(private readonly TemplateFinderInterface $templateFinder)
    {
    }

    public function parse(Token $token): Node
    {
        $macro = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect(Token::NAME_TYPE, 'as');
        $var = new AssignNameExpression($this->parser->getStream()->expect(Token::NAME_TYPE)->getValue(), $token->getLine());
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        $this->parser->addImportedSymbol('template', $var->getAttribute('name'));

        // sw-fix-start
        if ($macro instanceof ConstantExpression) {
            $macro->setAttribute('value', $this->templateFinder->find($macro->getAttribute('value')));
        }
        // sw-fix-end

        return new ImportNode($macro, $var, $token->getLine(), $this->parser->isMainScope());
    }

    public function getTag(): string
    {
        return 'sw_import';
    }
}
