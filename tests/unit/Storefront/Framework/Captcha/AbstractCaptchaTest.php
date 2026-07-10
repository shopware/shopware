<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(AbstractCaptcha::class)]
class AbstractCaptchaTest extends TestCase
{
    public function testValidateReturnsEmptyListForValidLegacyCaptcha(): void
    {
        $captcha = $this->createLegacyCaptcha(isValid: true, violations: new ConstraintViolationList());

        static::assertCount(0, $captcha->validate(new Request(), []));
    }

    public function testValidateReturnsLegacyViolationsForInvalidLegacyCaptcha(): void
    {
        $violation = new ConstraintViolation('', '', [], '', '', '', null, 'custom-violation-code');
        $captcha = $this->createLegacyCaptcha(isValid: false, violations: new ConstraintViolationList([$violation]));

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        static::assertSame($violation, $violations->get(0));
    }

    public function testValidateAddsGenericViolationForInvalidLegacyCaptchaWithoutViolations(): void
    {
        // An invalid captcha without violation details (e.g. the honeypot) must not
        // return an empty list, as an empty list signals a valid captcha.
        $captcha = $this->createLegacyCaptcha(isValid: false, violations: new ConstraintViolationList());

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    public function testValidateSilencesInheritedDeprecatedGetViolations(): void
    {
        // A legacy captcha that overrides neither getViolations() nor validate() runs
        // through the deprecated AbstractCaptcha::getViolations(), which the stub must
        // call inside Feature::silent() so core never triggers self-deprecations.
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
