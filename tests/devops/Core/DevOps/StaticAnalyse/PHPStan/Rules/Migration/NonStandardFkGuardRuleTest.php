<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Migration;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\NonStandardFkGuardRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NonStandardFkGuardRule>
 */
#[Package('framework')]
class NonStandardFkGuardRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $message = 'Raw DDL against "product" must go through MigrationStep::executeDdlStatement(), otherwise it '
            . 'fails on MySQL 8.4 for the shops that carry non-standard foreign key drift against that '
            . 'table (MySQL bug #118151).';

        $this->analyse([
            __DIR__ . '/../data/NonStandardFkGuardRule/Migration1785000001UnguardedDdl.php',
            __DIR__ . '/../data/NonStandardFkGuardRule/Migration1785000002GuardedDdl.php',
        ], [
            [$message, 20],
            [$message, 22],
            [$message, 24],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NonStandardFkGuardRule();
    }
}
