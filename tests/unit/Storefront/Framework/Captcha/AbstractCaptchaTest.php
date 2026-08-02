<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractCaptcha::class)]
class AbstractCaptchaTest extends TestCase
{
    public function testValidateThrowsWhenFeatureIsActive(): void
    {
        // validate() becomes abstract in 6.8, so relying on the delegating default must not stay silent.
        $captcha = $this->createLegacyCaptcha(isValid: true, violations: new ConstraintViolationList());

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Relying on the default implementation of %s::validate() is deprecated. Implement validate() in %s.',
            AbstractCaptcha::class,
            $captcha::class
        )));

        $captcha->validate(new Request(), []);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateReturnsEmptyListForValidLegacyCaptcha(): void
    {
        $captcha = $this->createLegacyCaptcha(isValid: true, violations: new ConstraintViolationList());

        static::assertCount(0, $captcha->validate(new Request(), []));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateReturnsLegacyViolationsForInvalidLegacyCaptcha(): void
    {
        $violation = new ConstraintViolation('', '', [], '', '', '', null, 'custom-violation-code');
        $captcha = $this->createLegacyCaptcha(isValid: false, violations: new ConstraintViolationList([$violation]));

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        static::assertSame($violation, $violations->get(0));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsGenericViolationForInvalidLegacyCaptchaWithoutViolations(): void
    {
        // An empty list signals a valid captcha, so a failure always needs a violation.
        $captcha = $this->createLegacyCaptcha(isValid: false, violations: new ConstraintViolationList());

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateSilencesInheritedDeprecatedGetViolations(): void
    {
        // The inherited getViolations() must be called silently so core never self-deprecates.
        $captcha = new class extends AbstractCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }

            public function getName(): string
            {
                return 'legacyCaptchaWithoutViolations';
            }
        };

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    /**
     * Mimics a captcha that only implements the deprecated isValid()/getViolations() pair,
     * like third-party captchas that do not yet override validate().
     */
    private function createLegacyCaptcha(bool $isValid, ConstraintViolationList $violations): AbstractCaptcha
    {
        return new class($isValid, $violations) extends AbstractCaptcha {
            public function __construct(
                private readonly bool $valid,
                private readonly ConstraintViolationList $legacyViolations
            ) {
            }

            public function isValid(Request $request, array $captchaConfig): bool
            {
                return $this->valid;
            }

            public function getViolations(): ConstraintViolationList
            {
                return $this->legacyViolations;
            }

            public function getName(): string
            {
                return 'legacyCaptcha';
            }
        };
    }
}
