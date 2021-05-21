<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Decoratable\DecoratableNotInstantiatedRule;
use Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableNotInstantiated\DecoratableClass;

/**
 * @extends RuleTestCase<DecoratableNotInstantiatedRule>
 */
class DecoratableNotInstantiatedRuleTest extends RuleTestCase
{
    public function testDecoratableImplementsImterface(): void
    {
        $this->analyse([
            __DIR__ . '/_fixtures/DecoratableNotInstantiated/Test.php',
        ], [
            [
                'The service "' . DecoratableClass::class . '" is marked as "@Decoratable", but is instantiated, use constructor injection via the DIC instead.',
                9,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DecoratableNotInstantiatedRule($this->createBroker());
    }
}
