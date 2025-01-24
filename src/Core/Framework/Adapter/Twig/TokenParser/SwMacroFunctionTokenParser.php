<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\MacroOverrideNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Error\SyntaxError;
use Twig\Node\BodyNode;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Expression\Variable\LocalVariable;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenParser\MacroTokenParser;

#[Package('framework')]
/**
 * @internal
 *
 * @see MacroTokenParser -> basically copied, we use our own Macro node,
 * that returns the actual instance of returned value instead of the markup
 *
 * @codeCoverageIgnore - Covered by @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
class SwMacroFunctionTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(Token::NAME_TYPE)->getValue();
        $arguments = $this->parseDefinition();

        $stream->expect(Token::BLOCK_END_TYPE);
        $this->parser->pushLocalScope();
        $body = $this->parser->subparse([$this, 'decideBlockEnd'], true);
        if ($token = $stream->nextIf(Token::NAME_TYPE)) {
            $value = $token->getValue();

            if ($value !== $name) {
                throw new SyntaxError(\sprintf('Expected endmacro for macro "%s" (but "%s" given).', $name, $value), $stream->getCurrent()->getLine(), $stream->getSourceContext());
            }
        }
        $this->parser->popLocalScope();
        $stream->expect(Token::BLOCK_END_TYPE);

        $this->parser->setMacro($name, new MacroOverrideNode($name, new BodyNode([$body]), $arguments, $lineno));

        return new EmptyNode($lineno);
    }

    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('end_sw_macro_function');
    }

    public function getTag(): string
    {
        return 'sw_macro_function';
    }

    private function parseDefinition(): ArrayExpression
    {
        $arguments = new ArrayExpression([], $this->parser->getCurrentToken()->getLine());
        $stream = $this->parser->getStream();
        $stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        while (!$stream->test(Token::PUNCTUATION_TYPE, ')')) {
            if (\count($arguments)) {
                $stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');

                // if the comma above was a trailing comma, early exit the argument parse loop
                if ($stream->test(Token::PUNCTUATION_TYPE, ')')) {
                    break;
                }
            }

            $token = $stream->expect(Token::NAME_TYPE, null, 'An argument must be a name');
            $name = new LocalVariable($token->getValue(), $this->parser->getCurrentToken()->getLine());
            if ($token = $stream->nextIf(Token::OPERATOR_TYPE, '=')) {
                $default = $this->parser->getExpressionParser()->parseExpression();
            } else {
                $default = new ConstantExpression(null, $this->parser->getCurrentToken()->getLine());
                $default->setAttribute('is_implicit', true);
            }

            if (!$this->checkConstantExpression($default)) {
                throw new SyntaxError('A default value for an argument must be a constant (a boolean, a string, a number, a sequence, or a mapping).', $token->getLine(), $stream->getSourceContext());
            }
            $arguments->addElement($default, $name);
        }
        $stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

        return $arguments;
    }

    // checks that the node only contains "constant" elements
    private function checkConstantExpression(Node $node): bool
    {
        if (!(
            $node instanceof ConstantExpression || $node instanceof ArrayExpression
            || $node instanceof NegUnary || $node instanceof PosUnary
        )) {
            return false;
        }

        foreach ($node as $n) {
            if (!$this->checkConstantExpression($n)) {
                return false;
            }
        }

        return true;
    }
}
