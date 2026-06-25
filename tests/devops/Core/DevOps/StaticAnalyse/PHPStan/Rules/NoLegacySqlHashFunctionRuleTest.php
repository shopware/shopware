<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoLegacySqlHashFunctionRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoLegacySqlHashFunctionRule>
 */
#[Package('framework')]
class NoLegacySqlHashFunctionRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoLegacySqlHashFunctionRule/LegacySqlHashUsage.php'], [
            [
                'Legacy SQL hash function detected in `executeStatement()`. Do not use MD5()/SHA1() in SQL, use SHA2() or compute hash in PHP.',
                13,
            ],
            [
                'Legacy SQL hash function detected in `executeQuery()`. Do not use MD5()/SHA1() in SQL, use SHA2() or compute hash in PHP.',
                16,
            ],
            [
                'Legacy SQL hash function detected in `prepare()`. Do not use MD5()/SHA1() in SQL, use SHA2() or compute hash in PHP.',
                18,
            ],
        ]);
    }

    /**
     * @return NoLegacySqlHashFunctionRule
     */
    protected function getRule(): Rule
    {
        return new NoLegacySqlHashFunctionRule();
    }
}
