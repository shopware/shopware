<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('discovery')]
abstract class AbstractCaptcha
{
    /**
     * supports returns true if this captcha needs to be valid for the request
     * to be let through. This may be determined based on the given request, but
     * also the shop's configuration or other sources.
     *
     * @param array<string, mixed> $captchaConfig
     */
    public function supports(Request $request, array $captchaConfig): bool
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return false;
        }

        if ($captchaConfig === []) {
            return false;
        }

        return (bool) $captchaConfig['isActive'];
    }

    /**
     * @internal implement {@see validate()}, this only keeps the deprecated pair dispatched
     *
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed, callers will use validate() directly
     *
     * @param array<string, mixed> $captchaConfig
     */
    final public function runValidation(Request $request, array $captchaConfig): ConstraintViolationList
    {
        if ($this->hasDeprecatedOverride()) {
            return $this->validateWithDeprecatedMethods($request, $captchaConfig);
        }

        return $this->validate($request, $captchaConfig);
    }

    /**
     * validate returns the violations describing the failure, an empty list means the captcha is valid.
     *
     * @param array<string, mixed> $captchaConfig
     */
    #[BecomesAbstract(version: 'v6.8.0', description: 'The default implementation that delegates to the deprecated isValid()/getViolations() will be removed.')]
    public function validate(Request $request, array $captchaConfig): ConstraintViolationList
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf('Relying on the default implementation of %s::validate() is deprecated. Implement validate() in %s.', self::class, static::class)
        );

        return $this->validateWithDeprecatedMethods($request, $captchaConfig);
    }

    /**
     * isValid returns true, when the captcha contained in the request is valid.
     *
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, implement validate() instead
     *
     * @param array<string, mixed> $captchaConfig
     */
    abstract public function isValid(Request $request, array $captchaConfig): bool;

    /**
     * getName returns a unique technical name identifying this captcha.
     */
    abstract public function getName(): string;

    /**
     * Returns true when the CAPTCHA doesn't need to provide information on how to pass
     * the check to customers. An exception will be thrown instead as soon as the CAPTCHA check fails
     */
    public function shouldBreak(): bool
    {
        return true;
    }

    /**
     * getData returns data the captcha might need to render in the template for
     * the user to be able to correctly fill in the captcha value, for example
     * an image of distorted text.
     *
     * @return array<string|int, mixed>|null
     */
    public function getData(): ?array
    {
        return null;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use validate() instead
     */
    public function getViolations(): ConstraintViolationList
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'validate()'));

        return new ConstraintViolationList();
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed together with isValid()/getViolations()
     *
     * @param array<string, mixed> $captchaConfig
     */
    private function validateWithDeprecatedMethods(Request $request, array $captchaConfig): ConstraintViolationList
    {
        if (Feature::silent('v6.8.0.0', fn (): bool => $this->isValid($request, $captchaConfig))) {
            return new ConstraintViolationList();
        }

        $violations = Feature::silent('v6.8.0.0', fn (): ConstraintViolationList => $this->getViolations());
        if ($violations->count() === 0) {
            // An empty list would read as valid, so a failure always needs a violation.
            $violations->add(new ConstraintViolation('', '', [], '', '', '', null, CaptchaException::INVALID_CAPTCHA_ERROR));
        }

        return $violations;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed together with isValid()/getViolations()
     */
    private function hasDeprecatedOverride(): bool
    {
        $providesValidate = (new \ReflectionMethod($this, 'validate'))->getDeclaringClass()->getName();

        foreach (['isValid', 'getViolations'] as $method) {
            // Strictly below: a captcha declares isValid() itself and inherits getViolations().
            $declaringClass = (new \ReflectionMethod($this, $method))->getDeclaringClass()->getName();
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
