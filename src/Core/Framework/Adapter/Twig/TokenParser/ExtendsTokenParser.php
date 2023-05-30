<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Node;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('core')]
final class ExtendsTokenParser extends AbstractTokenParser
{
    /**
     * @var Parser
     */
    protected $parser;

    public function __construct(private readonly TemplateFinderInterface $finder)
    {
    }

    /**
     * @return Node
     */
    public function parse(Token $token)
    {
        //get full token stream to inject extends token for inheritance
        $stream = $this->parser->getStream();

        $source = $stream->getSourceContext()->getName();

        $template = $stream->next()->getValue();

        //resolves parent template
        //set pointer to next value (contains the template file name)
        $parent = $this->finder->find($template, false, $source);

        //set pointer to end of line - BLOCK_END_TYPE
        $stream->next();

        $stream->injectTokens([
            new Token(Token::BLOCK_START_TYPE, '', 2),
            new Token(Token::NAME_TYPE, 'extends', 2),
            new Token(Token::STRING_TYPE, $parent, 2),
            new Token(Token::BLOCK_END_TYPE, '', 2),
        ]);

        return new Node();
    }

    public function getTag(): string
    {
        return 'sw_extends';
    }
}
