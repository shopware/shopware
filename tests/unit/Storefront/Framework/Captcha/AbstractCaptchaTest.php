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
        // An empty list signals a valid captcha, so a failure always needs a violation.
        $captcha = $this->createLegacyCaptcha(isValid: false, violations: new ConstraintViolationList());

        $violations = $captcha->validate(new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    public function testValidateSilencesTheInheritedDeprecatedGetViolations(): void
    {
        // The inherited getViolations() is called silently so core never self-deprecates.
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
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testIsValidDelegatesToValidateForAMigratedCaptcha(): void
    {
        // A captcha implementing only validate() must not have to implement isValid() as well.
        static::assertTrue($this->createMigratedCaptcha(new ConstraintViolationList())->isValid(new Request(), []));

        $failing = $this->createMigratedCaptcha(new ConstraintViolationList([
            new ConstraintViolation('', '', [], '', '', '', null, 'native-code'),
        ]));
        static::assertFalse($failing->isValid(new Request(), []));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testInheritedIsValidThrowsWhenFeatureIsActive(): void
    {
        // Overriding isValid() stays notice-free; only the inherited default deprecates.
        $captcha = $this->createMigratedCaptcha(new ConstraintViolationList());

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "%s::isValid()" is deprecated and will be removed in v6.8.0.0. Use "validate()" instead.',
            AbstractCaptcha::class
        )));

        $captcha->isValid(new Request(), []);
    }

    private function createMigratedCaptcha(ConstraintViolationList $violations): AbstractCaptcha
    {
        return new class($violations) extends AbstractCaptcha {
            public function __construct(private readonly ConstraintViolationList $nativeViolations)
            {
            }

            public function validate(Request $request, array $captchaConfig): ConstraintViolationList
            {
                return $this->nativeViolations;
            }

            public function getName(): string
            {
                return 'migratedCaptcha';
            }
        };
    }

    /**
     * A third-party captcha that implements only the deprecated isValid()/getViolations() pair.
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
