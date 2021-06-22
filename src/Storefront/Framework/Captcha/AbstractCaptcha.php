<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;

abstract class AbstractCaptcha
{
    /**
     * supports returns true if this captcha needs to be valid for the request
     * to be let through. This may be determined based on the given request, but
     * also the shop's configuration or other sources.
     *
     * @deprecated tag:v6.5.0 - Parameter $captchaConfig will be mandatory in future implementation
     */
    public function supports(Request $request /* , array $captchaConfig = [] */): bool
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return false;
        }

        $captchaConfig = \func_get_args()[1] ?? [];

        if (empty($captchaConfig)) {
            return false;
        }

        return (bool) $captchaConfig['isActive'];
    }

    /**
     * isValid returns true, when the captcha contained in the request is valid.
     *
     * @deprecated tag:v6.5.0 - Parameter $captchaConfig will be mandatory in future implementation
     */
    abstract public function isValid(Request $request /* , array $captchaConfig = [] */): bool;

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
     */
    public function getData(): ?array
    {
        return null;
    }

    public function getViolations(): ConstraintViolationList
    {
        return new ConstraintViolationList();
    }
}
