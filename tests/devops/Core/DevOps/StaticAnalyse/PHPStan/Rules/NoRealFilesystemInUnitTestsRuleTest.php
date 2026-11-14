<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoRealFilesystemInUnitTestsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoRealFilesystemInUnitTestsRule>
 */
#[Package('framework')]
class NoRealFilesystemInUnitTestsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoRealFilesystemInUnitTestsRule/Cases.php'], [
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 19],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'tempnam'), 20],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'tmpfile'), 21],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'getcwd'), 22],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chdir'), 23],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 24],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'mkdir'), 29],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'rmdir'), 30],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'unlink'), 31],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'rename'), 32],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'copy'), 33],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'touch'), 34],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'symlink'), 35],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'link'), 36],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chmod'), 37],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chown'), 38],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chgrp'), 39],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'file_put_contents'), 40],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'mkdir'), 47],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'dumpFile'), 48],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'appendToFile'), 49],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'touch'), 50],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'copy'), 51],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'rename'), 52],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'mirror'), 53],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'symlink'), 54],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'hardlink'), 55],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chmod'), 56],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chown'), 57],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chgrp'), 58],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'tempnam'), 59],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'remove'), 60],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 103],
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoRealFilesystemInUnitTestsRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoRealFilesystemInUnitTestsRule(
            $this->createReflectionProvider(),
            new Configuration(['realFilesystemInUnitTestsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\']]),
        );
    }
}
