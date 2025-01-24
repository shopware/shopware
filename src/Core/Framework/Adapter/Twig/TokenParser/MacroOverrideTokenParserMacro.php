<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Log\Package;
use Twig\Token;

#[Package('framework')]
/**
 * @internal
 *
 * deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed use `sw_macro_function` instead of macro in app scripts
 * we can not use @ deprecated, as the phpstorm plugin would mark all macros as deprecated
 *
 * @codeCoverageIgnore - Covered by @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
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
