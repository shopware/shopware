<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\ExplicitIdnConversionOptionsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<ExplicitIdnConversionOptionsRule>
 */
#[Package('framework')]
class ExplicitIdnConversionOptionsRuleTest extends RuleTestCase
{
    private const ERROR = 'Pass IDNA_NONTRANSITIONAL_TO_ASCII and INTL_IDNA_VARIANT_UTS46 explicitly to idn_to_ascii().';
    private const UTF8_ERROR = 'Pass IDNA_NONTRANSITIONAL_TO_UNICODE and INTL_IDNA_VARIANT_UTS46 explicitly to idn_to_utf8().';

    public function testRequiresNonTransitionalFlagsAndUts46Variant(): void
    {
        $this->analyse([__DIR__ . '/data/ExplicitIdnConversionOptionsRule/Conversions.php'], [
            [self::ERROR, 5],
            [self::ERROR, 6],
            [self::ERROR, 7],
            [self::ERROR, 8],
            [self::UTF8_ERROR, 11],
            [self::UTF8_ERROR, 12],
            [self::UTF8_ERROR, 13],
            [self::ERROR, 16],
        ]);
    }

    /**
     * @return ExplicitIdnConversionOptionsRule
     */
    protected function getRule(): Rule
    {
        return new ExplicitIdnConversionOptionsRule($this->createReflectionProvider());
    }
}
