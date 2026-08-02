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
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed together with isValid()/getViolations()
     */
    private bool $dispatchingDeprecatedOverride = false;

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
     * validate checks the captcha contained in the request and returns the violations
     * describing the failure. An empty list means the captcha is valid.
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
     * Runs the captcha through the deprecated isValid()/getViolations() pair, so implementations that
     * still only provide those keep working. Called from inside the core, therefore it does not trigger
     * a deprecation itself — the caller does.
     *
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed together with isValid()/getViolations()
     *
     * @param array<string, mixed> $captchaConfig
     */
    protected function validateWithDeprecatedMethods(Request $request, array $captchaConfig): ConstraintViolationList
    {
        $this->dispatchingDeprecatedOverride = true;

        try {
            if (Feature::silent('v6.8.0.0', fn (): bool => $this->isValid($request, $captchaConfig))) {
                return new ConstraintViolationList();
            }

            $violations = Feature::silent('v6.8.0.0', fn (): ConstraintViolationList => $this->getViolations());
            if ($violations->count() === 0) {
                // An empty list means valid, so a captcha without violation details still needs one.
                $violations->add(new ConstraintViolation('', '', [], '', '', '', null, CaptchaException::INVALID_CAPTCHA_ERROR));
            }

            return $violations;
        } finally {
            $this->dispatchingDeprecatedOverride = false;
        }
    }

    /**
     * Returns true when a class below $captcha still overrides one of the deprecated methods. Captchas
     * implementing validate() natively call this first and hand over to validateWithDeprecatedMethods(),
     * so a subclass written against the pre-validate() API keeps deciding until it is removed in 6.8.
     * Pass self::class from the captcha whose native validate() is running.
     *
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be removed together with isValid()/getViolations()
     */
    protected function hasDeprecatedOverride(string $captcha): bool
    {
        // The overridden method called back into validate(). Run the native check instead of dispatching
        // into it a second time, which would recurse until the process runs out of memory.
        if ($this->dispatchingDeprecatedOverride) {
            return false;
        }

        foreach (['isValid', 'getViolations'] as $method) {
            // Only a class *below* $captcha counts: the captcha declaring the native validate() implements
            // isValid() itself, and inherits getViolations() from an ancestor.
            $declaringClass = (new \ReflectionMethod($this, $method))->getDeclaringClass()->getName();
            if (!is_subclass_of($declaringClass, $captcha)) {
                continue;
            }

            // Unlike the core captchas this one cannot be migrated for the extension author, so it has to
            // be nudged here — validateWithDeprecatedMethods() silences the deprecations of the pair itself.
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf('Overriding %s::%s() is deprecated, implement validate() instead.', $declaringClass, $method)
            );

            return true;
        }

        return false;
    }
}
