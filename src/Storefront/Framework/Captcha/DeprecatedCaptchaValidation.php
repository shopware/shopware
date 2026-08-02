<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Keeps captchas that still override the deprecated isValid()/getViolations() dispatched until they are removed.
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed, callers will use AbstractCaptcha::validate() directly
 */
#[Package('discovery')]
final class DeprecatedCaptchaValidation
{
    /**
     * @param array<string, mixed> $captchaConfig
     */
    public static function validate(AbstractCaptcha $captcha, Request $request, array $captchaConfig): ConstraintViolationList
    {
        if (self::hasDeprecatedOverride($captcha)) {
            return self::fromDeprecatedMethods($captcha, $request, $captchaConfig);
        }

        return $captcha->validate($request, $captchaConfig);
    }

    /**
     * @param array<string, mixed> $captchaConfig
     */
    public static function fromDeprecatedMethods(AbstractCaptcha $captcha, Request $request, array $captchaConfig): ConstraintViolationList
    {
        if (Feature::silent('v6.8.0.0', fn (): bool => $captcha->isValid($request, $captchaConfig))) {
            return new ConstraintViolationList();
        }

        $violations = Feature::silent('v6.8.0.0', fn (): ConstraintViolationList => $captcha->getViolations());
        if ($violations->count() === 0) {
            // An empty list would read as valid, so a failure always needs a violation.
            $violations->add(new ConstraintViolation('', '', [], '', '', '', null, CaptchaException::INVALID_CAPTCHA_ERROR));
        }

        return $violations;
    }

    private static function hasDeprecatedOverride(AbstractCaptcha $captcha): bool
    {
        $providesValidate = (new \ReflectionMethod($captcha, 'validate'))->getDeclaringClass()->getName();

        foreach (['isValid', 'getViolations'] as $method) {
            // Strictly below: a captcha declares isValid() itself and inherits getViolations().
            $declaringClass = (new \ReflectionMethod($captcha, $method))->getDeclaringClass()->getName();
            if (!is_subclass_of($declaringClass, $providesValidate)) {
                continue;
            }

            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf('Overriding %s::%s() is deprecated, implement validate() instead.', $declaringClass, $method)
            );

            return true;
        }

        return false;
    }
}
