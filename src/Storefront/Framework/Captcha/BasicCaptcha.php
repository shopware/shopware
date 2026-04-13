<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('framework')]
class BasicCaptcha extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'basicCaptcha';
    final public const CAPTCHA_REQUEST_PARAMETER = 'shopware_basic_captcha_confirm';
    final public const BASIC_CAPTCHA_SESSION = 'basic_captcha_session';
    final public const INVALID_CAPTCHA_CODE = 'captcha.basic-captcha-invalid';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function supports(Request $request, array $captchaConfig): bool
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $salesChannelId = $context instanceof SalesChannelContext ? $context->getSalesChannelId() : null;

        $activeCaptchas = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $salesChannelId);

        if (!\is_array($activeCaptchas) || $activeCaptchas === []) {
            return false;
        }

        return $request->isMethod(Request::METHOD_POST)
            && \array_key_exists(self::CAPTCHA_NAME, $activeCaptchas)
            && $activeCaptchas[self::CAPTCHA_NAME]['isActive'];
    }

    public function isValid(Request $request, array $captchaConfig): bool
    {
        $basicCaptchaValue = $request->request->get(self::CAPTCHA_REQUEST_PARAMETER);

        if ($basicCaptchaValue === null) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $captchaSession = $session->get(RequestParamHelper::get($request, 'formId') . self::BASIC_CAPTCHA_SESSION);
        $session->remove(RequestParamHelper::get($request, 'formId') . self::BASIC_CAPTCHA_SESSION);

        if ($captchaSession === null) {
            return false;
        }

        return strtolower((string) $basicCaptchaValue) === strtolower((string) $captchaSession);
    }

    public function shouldBreak(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }

    public function getViolations(): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            '',
            '',
            [],
            '',
            '/' . self::CAPTCHA_REQUEST_PARAMETER,
            '',
            null,
            self::INVALID_CAPTCHA_CODE
        ));

        return $violations;
    }
}
