<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Decoratable\DecoratableNotDirectlyDependetRule;
use Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableNotDirectlyDependet\DecoratableClass;
use Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableNotDirectlyDependet\Test;

/**
 * @extends RuleTestCase<DecoratableNotDirectlyDependetRule>
 */
class DecoratableNotDirectlyDependetRuleTest extends RuleTestCase
{
    private const ERROR_MSG = 'The service "' . Test::class . '" has a direct dependency on decoratable service "' . DecoratableClass::class . '", but must only depend on it\'s interface.';

    public function testDecoratableImplementsImterface(): void
    {
        $this->analyse([
            __DIR__ . '/_fixtures/DecoratableNotDirectlyDependet/Test.php',
        ], [
            [
                self::ERROR_MSG,
                10,
            ],
            [
                self::ERROR_MSG,
                17,
            ],
            [
                self::ERROR_MSG,
                23,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DecoratableNotDirectlyDependetRule($this->createBroker());
    }
}
