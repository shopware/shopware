<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\NoClassAliasExpressionUsageRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage;

/**
 * @internal
 *
 * @extends RuleTestCase<NoClassAliasExpressionUsageRule>
 */
#[Package('framework')]
class NoClassAliasExpressionUsageRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testOldClassNameUsagesAreReported(): void
    {
        require_once __DIR__ . '/data/BCChangeAttributeUsageRule/ClassMovedAttributeUsage.php';
        class_alias(ClassMovedAttributeUsage::class, 'Shopware\Tests\Legacy\UnregisteredClass');

        $message = 'Class alias "Shopware\Tests\Legacy\UnregisteredClass" is kept only for backwards compatibility. Use "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage" instead.';

        $this->analyse([__DIR__ . '/data/NoClassAliasUsageRule/ClassAliasUsage.php'], [
            [$message, 17],
            [$message, 19],
            [$message, 20],
            [$message, 21],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoClassAliasExpressionUsageRule(new ClassAliasMap());
    }
}
