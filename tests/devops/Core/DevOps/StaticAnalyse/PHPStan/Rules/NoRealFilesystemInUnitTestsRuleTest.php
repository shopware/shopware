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
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 18],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'tempnam'), 19],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'tmpfile'), 20],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'getcwd'), 21],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chdir'), 22],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 23],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'mkdir'), 28],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'rmdir'), 29],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'unlink'), 30],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'rename'), 31],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'copy'), 32],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'touch'), 33],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'symlink'), 34],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'link'), 35],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chmod'), 36],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chown'), 37],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'chgrp'), 38],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'file_put_contents'), 39],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'mkdir'), 46],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'dumpFile'), 47],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'appendToFile'), 48],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'touch'), 49],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'copy'), 50],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'rename'), 51],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'mirror'), 52],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'symlink'), 53],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'hardlink'), 54],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chmod'), 55],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chown'), 56],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'chgrp'), 57],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'tempnam'), 58],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_METHOD, 'remove'), 59],
            [\sprintf(NoRealFilesystemInUnitTestsRule::ERROR_FUNCTION, 'sys_get_temp_dir'), 98],
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
