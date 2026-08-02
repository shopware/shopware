<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\DeprecatedCaptchaValidation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Remove together with DeprecatedCaptchaValidation
 */
#[Package('discovery')]
#[CoversClass(DeprecatedCaptchaValidation::class)]
class DeprecatedCaptchaValidationTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCaptchaImplementingValidateIsNotRoutedThroughTheDeprecatedMethods(): void
    {
        $captcha = self::createNativeCaptcha();

        $violations = DeprecatedCaptchaValidation::validate($captcha, new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('native', $violation->getCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingIsValidIsRoutedThroughTheDeprecatedMethods(): void
    {
        $captcha = new class extends CaptchaWithValidate {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return $request->request->get('custom-check') === 'pass';
            }
        };

        static::assertCount(0, DeprecatedCaptchaValidation::validate($captcha, new Request(request: ['custom-check' => 'pass']), []));
        static::assertCount(1, DeprecatedCaptchaValidation::validate($captcha, new Request(), []));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingGetViolationsIsRoutedThroughTheDeprecatedMethods(): void
    {
        $captcha = new class extends CaptchaWithValidate {
            public function getViolations(): ConstraintViolationList
            {
                return new ConstraintViolationList([
                    new ConstraintViolation('', '', [], '', '', '', null, 'plugin-custom-code'),
                ]);
            }
        };

        $violations = DeprecatedCaptchaValidation::validate($captcha, new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('plugin-custom-code', $violation->getCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCaptchaImplementingOnlyIsValidIsRoutedThroughTheDeprecatedMethods(): void
    {
        // A captcha written before validate() existed: it inherits the default validate().
        $captcha = new class extends AbstractCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }

            public function getName(): string
            {
                return 'pre-6.7-captcha';
            }
        };

        $violations = DeprecatedCaptchaValidation::validate($captcha, new Request(), []);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    public function testSubclassOverridingIsValidThrowsWhenFeatureIsActive(): void
    {
        $captcha = new class extends CaptchaWithValidate {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }
        };

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Overriding %s::isValid() is deprecated, implement validate() instead.',
            $captcha::class
        )));

        DeprecatedCaptchaValidation::validate($captcha, new Request(), []);
    }

    public function testNativeCaptchaDoesNotTriggerADeprecationWhenFeatureIsActive(): void
    {
        static::assertCount(1, DeprecatedCaptchaValidation::validate(self::createNativeCaptcha(), new Request(), []));
    }

    private static function createNativeCaptcha(): AbstractCaptcha
    {
        return new class extends CaptchaWithValidate {};
    }
}

/**
 * A captcha that implements validate() itself, like every core captcha does.
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - Remove together with DeprecatedCaptchaValidation
 */
#[Package('discovery')]
abstract class CaptchaWithValidate extends AbstractCaptcha
{
    public function validate(Request $request, array $captchaConfig): ConstraintViolationList
    {
        return new ConstraintViolationList([
            new ConstraintViolation('', '', [], '', '', '', null, 'native'),
        ]);
    }

    public function isValid(Request $request, array $captchaConfig): bool
    {
        return $this->validate($request, $captchaConfig)->count() === 0;
    }

    public function getName(): string
    {
        return 'captcha-with-validate';
    }
}
