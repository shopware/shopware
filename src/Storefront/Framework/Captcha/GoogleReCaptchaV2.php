<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal (flag:FEATURE_NEXT_12455)
 */
class GoogleReCaptchaV2 extends AbstractCaptcha
{
    public const CAPTCHA_NAME = 'googleReCaptchaV2';
    public const CAPTCHA_REQUEST_PARAMETER = '_grecaptcha_v2';

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        // TODO: NEXT-14133 - Integrate Google reCaptcha v3 server side
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request): bool
    {
        // TODO: NEXT-14133 - Integrate Google reCaptcha v3 server side
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
