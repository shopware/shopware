<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\NoClassAliasUsageRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage;

/**
 * @internal
 *
 * @extends RuleTestCase<NoClassAliasUsageRule>
 */
#[Package('framework')]
class NoClassAliasUsageRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testOldClassNameUsagesAreReported(): void
    {
        require_once __DIR__ . '/data/BCChangeAttributeUsageRule/ClassMovedAttributeUsage.php';
        class_alias(ClassMovedAttributeUsage::class, 'Shopware\Tests\Legacy\UnregisteredClass');

        $message = 'Class alias "Shopware\Tests\Legacy\UnregisteredClass" is kept only for backwards compatibility. Use "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage" instead.';

        $this->analyse([__DIR__ . '/data/NoClassAliasUsageRule/ClassAliasUsage.php'], [
            [$message, 10],
            [$message, 14],
            [$message, 14],
            [$message, 17],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoClassAliasUsageRule(new ClassAliasMap());
    }
}
