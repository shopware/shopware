<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Decoratable\DecoratableDoesNotCallOwnPublicMethodRule;
use Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableDoesNotCallOwnPublicMethod\DecoratableDoesCallOwnPublicMethod;

/**
 * @extends RuleTestCase<DecoratableDoesNotCallOwnPublicMethodRule>
 */
class DecoratableDoesNotCallOwnPublicMethodRuleTest extends RuleTestCase
{
    public function testDecoratableDoesNotCallOwnPublicMethod(): void
    {
        $this->analyse([
            __DIR__ . '/_fixtures/DecoratableDoesNotCallOwnPublicMethod/DecoratableDoesCallOwnPublicMethod.php',
        ], [
            [
                'The service "' . DecoratableDoesCallOwnPublicMethod::class . '" is marked as "@Decoratable", but calls it\'s own public method "build", which breaks decoration.',
                14,
            ],
        ]);
    }

    public function testNotTaggedClassIsAllowedToCallOwnPublicMethod(): void
    {
        $this->analyse([
            __DIR__ . '/_fixtures/DecoratableDoesNotCallOwnPublicMethod/NotTaggedClassIsAllowedToCallOwnPublicMethod.php',
        ], []);
    }

    protected function getRule(): Rule
    {
        return new DecoratableDoesNotCallOwnPublicMethodRule();
    }
}
