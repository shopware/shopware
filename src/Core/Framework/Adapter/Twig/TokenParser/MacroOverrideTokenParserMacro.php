<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\MacroOverrideNode;
use Shopware\Core\Framework\Adapter\Twig\Node\ReturnNode;
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
 * deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed use `sw_macro_function` instead of macro in app scripts
 * we can not use @ deprecated, as the phpstorm plugin would mark all macros as deprecated
 */
class MacroOverrideTokenParserMacro extends SwMacroFunctionTokenParser
{
    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endmacro');
    }

    public function getTag(): string
    {
        return 'macro';
    }
}
