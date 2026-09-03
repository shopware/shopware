<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoKernelBootInSetUpBeforeClassRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoKernelBootInSetUpBeforeClassRule>
 */
#[Package('framework')]
class NoKernelBootInSetUpBeforeClassRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoKernelBootInSetUpBeforeClassRule/Cases.php'], [
            // BootsInStaticHooks: bootKernel() in setUpBeforeClass()
            [NoKernelBootInSetUpBeforeClassRule::ERROR_STATIC_BOOT, 15],
            // BootsInStaticHooks: bootKernel() in tearDownAfterClass()
            [NoKernelBootInSetUpBeforeClassRule::ERROR_STATIC_BOOT, 20],
            // CreatesKernelInStaticHook: createKernel() in setUpBeforeClass()
            [NoKernelBootInSetUpBeforeClassRule::ERROR_STATIC_BOOT, 31],
            // NOT flagged: BootsLazily (lazy boot in setUp), SomeSupportClass (not a TestCase subclass)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoKernelBootInSetUpBeforeClassRule();
    }
}
