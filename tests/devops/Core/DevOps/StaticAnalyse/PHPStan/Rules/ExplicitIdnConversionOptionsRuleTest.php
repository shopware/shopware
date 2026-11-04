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
    private const ASCII_TIP = 'Use IDNA_NONTRANSITIONAL_TO_ASCII and INTL_IDNA_VARIANT_UTS46 as the flags and variant arguments.';
    private const UTF8_TIP = 'Use IDNA_NONTRANSITIONAL_TO_UNICODE and INTL_IDNA_VARIANT_UTS46 as the flags and variant arguments.';

    public function testRequiresNonTransitionalFlagsAndUts46Variant(): void
    {
        $this->analyse([__DIR__ . '/data/ExplicitIdnConversionOptionsRule/Conversions.php'], [
            [self::ERROR, 9, self::ASCII_TIP],
            [self::ERROR, 10, self::ASCII_TIP],
            [self::ERROR, 11, self::ASCII_TIP],
            [self::ERROR, 12, self::ASCII_TIP],
            [self::UTF8_ERROR, 15, self::UTF8_TIP],
            [self::UTF8_ERROR, 16, self::UTF8_TIP],
            [self::UTF8_ERROR, 17, self::UTF8_TIP],
            [self::ERROR, 20, self::ASCII_TIP],
            [self::ERROR, 24, self::ASCII_TIP],
            [self::UTF8_ERROR, 25, self::UTF8_TIP],
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
