<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\DeprecatedCaptchaValidation;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(HoneypotCaptcha::class)]
class HoneypotCaptchaTest extends TestCase
{
    /**
     * @param array<string, mixed> $requestParameters
     */
    #[DataProvider('honeypotProvider')]
    #[TestDox('The honeypot captcha is valid only when the honeypot field is empty')]
    public function testValidate(array $requestParameters, bool $expectedValid): void
    {
        Feature::fake(['v6.8.0.0'], function () use ($requestParameters, $expectedValid): void {
            $captcha = new HoneypotCaptcha(static::createStub(ValidatorInterface::class));

            static::assertSame(
                $expectedValid,
                $captcha->validate(new Request(request: $requestParameters), [])->count() === 0
            );
        });
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('deprecated isValid() validates through the Symfony validator before 6.8')]
    public function testDeprecatedIsValidUsesValidator(): void
    {
        // Before 6.8 the honeypot is checked with the Symfony validator.
        $emptyValidator = static::createStub(ValidatorInterface::class);
        $emptyValidator->method('validate')->willReturn(new ConstraintViolationList());
        static::assertTrue((new HoneypotCaptcha($emptyValidator))->isValid(new Request(), []));

        $failingValidator = static::createStub(ValidatorInterface::class);
        $failingValidator->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('not blank', '', [], '', 'honeypotValue', 'i-am-a-bot'),
        ]));
        static::assertFalse((new HoneypotCaptcha($failingValidator))->isValid(
            new Request(request: [HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'i-am-a-bot']),
            []
        ));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedIsValidIsStillDispatched(): void
    {
        // isValid() was the only hook before validate(), so it has to keep deciding until 6.8.
        $captcha = new class($this->createValidator()) extends HoneypotCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return $request->request->get('custom-check') === 'pass';
            }
        };

        // The honeypot is empty, so the native check would let this through.
        static::assertCount(1, DeprecatedCaptchaValidation::validate($captcha, new Request(request: ['custom-check' => 'fail']), []));
        static::assertCount(0, DeprecatedCaptchaValidation::validate($captcha, new Request(request: ['custom-check' => 'pass']), []));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated getViolations() method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubclassOverridingDeprecatedGetViolationsIsStillDispatched(): void
    {
        // A filled honeypot, so the native check rejects and the violations are consulted.
        $captcha = new class($this->createValidator(isHoneypotEmpty: false)) extends HoneypotCaptcha {
            public function getViolations(): ConstraintViolationList
            {
                return new ConstraintViolationList([
                    new ConstraintViolation('', '', [], '', '', '', null, 'plugin-custom-code'),
                ]);
            }
        };

        $violations = DeprecatedCaptchaValidation::validate(
            $captcha,
            new Request(request: [HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'i-am-a-bot']),
            []
        );

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('plugin-custom-code', $violation->getCode());
        static::assertNotSame(CaptchaException::INVALID_CAPTCHA_ERROR, $violation->getCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testSubclassOverridingDeprecatedIsValidThrowsWhenFeatureIsActive(): void
    {
        // The pair is gone in 6.8, so relying on it has to fail loudly instead of being dropped.
        $captcha = new class($this->createValidator()) extends HoneypotCaptcha {
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

    /**
     * @return \Generator<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function honeypotProvider(): \Generator
    {
        yield 'absent honeypot field is valid' => [[], true];
        yield 'empty honeypot field is valid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => ''], true];
        // InputBag::get() returns a present-but-null value as null, which still means empty.
        yield 'present-but-null honeypot field is valid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => null], true];
        yield 'filled honeypot field is invalid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'i-am-a-bot'], false];
    }

    private function createValidator(bool $isHoneypotEmpty = true): ValidatorInterface
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($isHoneypotEmpty
            ? new ConstraintViolationList()
            : new ConstraintViolationList([new ConstraintViolation('', '', [], '', '', '', null, 'filled')]));

        return $validator;
    }
}
