<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCaptcha
{
    /**
     * supports returns true if this captcha needs to be valid for the request
     * to be let through. This may be determined based on the given request, but
     * also the shop's configuration or other sources.
     */
    abstract public function supports(Request $request): bool;

    /**
     * isValid returns true, when the captcha contained in the request is valid.
     */
    abstract public function isValid(Request $request): bool;

    /**
     * getName returns a unique technical name identifying this captcha.
     */
    abstract public function getName(): string;

    /**
     * getData returns data the captcha might need to render in the template for
     * the user to be able to correctly fill in the captcha value, for example
     * an image of distorted text.
     */
    public function getData(): ?array
    {
        return null;
    }
}
