<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

#[Package('core')]
final class ExtendsTokenParser extends AbstractTokenParser
{
    /**
     * @var Parser
     */
    protected $parser;

    public function __construct(
        private readonly TemplateFinderInterface $finder,
        private readonly TemplateScopeDetector $templateScopeDetector,
    ) {
    }

    public function parse(Token $token): Node
    {
        // get full token stream to inject extends token for inheritance
        $stream = $this->parser->getStream();

        $source = $stream->getSourceContext()->getName();

        $options = $this->getOptions($stream);

        // resolves parent template
        // set pointer to next value (contains the template file name)
        $parent = $this->finder->find($options['template'], false, $source);

        // set pointer to end of line - BLOCK_END_TYPE
        do {
            $next = $stream->next();
        } while (!$next->test(Token::BLOCK_END_TYPE));

        $tokens = [
            new Token(Token::BLOCK_START_TYPE, '', 2),
            new Token(Token::NAME_TYPE, 'extends', 2),
            new Token(Token::STRING_TYPE, $parent, 2),
            new Token(Token::BLOCK_END_TYPE, '', 2),
        ];

        if ($this->shouldEndFile($options['scopes'], $source)) {
            $tokens[] = new Token(Token::EOF_TYPE, '', $token->getLine());
        }

        $stream->injectTokens($tokens);

        return new Node();
    }

    public function getTag(): string
    {
        return 'sw_extends';
    }

    /**
     * @return array{template: string, scopes: string[]}
     */
    private function getOptions(TokenStream $stream): array
    {
        if ($stream->test(Token::STRING_TYPE)) {
            return [
                'scopes' => [TemplateScopeDetector::DEFAULT_SCOPE],
                'template' => $stream->next()->getValue(),
            ];
        }

        $expression = $this->parser->getExpressionParser()->parseExpression();
        $options = $this->convertExpressionToArray($expression);

        if (!isset($options['template']) || !\is_string($options['template'])) {
            throw AdapterException::missingExtendsTemplate($stream->getSourceContext()->getName());
        }

        if (!isset($options['scopes'])) {
            $options['scopes'] = [TemplateScopeDetector::DEFAULT_SCOPE];
        }

        if (\is_string($options['scopes'])) {
            $options['scopes'] = [$options['scopes']];
        }

        return $options;
    }

    /**
     * @param string[] $scopes
     */
    private function shouldEndFile(array $scopes, string $source): bool
    {
        return !\array_intersect($this->templateScopeDetector->getScopes(), $scopes) && !str_starts_with($source, '@Storefront');
    }

    private function convertExpressionToArray(AbstractExpression $expression): mixed
    {
        if ($expression instanceof ArrayExpression) {
            $array = [];
            foreach ($expression->getKeyValuePairs() as $pair) {
                if (!$pair['key'] instanceof ConstantExpression) {
                    throw AdapterException::unexpectedTwigExpression($pair['key']);
                }

                $array[$pair['key']->getAttribute('value')] = $this->convertExpressionToArray($pair['value']);
            }

            return $array;
        }

        if ($expression instanceof ConstantExpression) {
            return $expression->getAttribute('value');
        }

        throw AdapterException::unexpectedTwigExpression($expression);
    }
}
