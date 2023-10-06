<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('storefront')]
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

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, array $captchaConfig): bool
    {
        /** @var SalesChannelContext|null $context */
        $context = $request->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $salesChannelId = $context ? $context->getSalesChannelId() : null;

        $activeCaptchas = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $salesChannelId);

        if (empty($activeCaptchas) || !\is_array($activeCaptchas)) {
            return false;
        }

        return $request->isMethod(Request::METHOD_POST)
            && \in_array(self::CAPTCHA_NAME, array_keys($activeCaptchas), true)
            && $activeCaptchas[self::CAPTCHA_NAME]['isActive'];
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        $basicCaptchaValue = $request->get(self::CAPTCHA_REQUEST_PARAMETER);

        if ($basicCaptchaValue === null) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $captchaSession = $session->get($request->get('formId') . self::BASIC_CAPTCHA_SESSION);
        $session->remove($request->get('formId') . self::BASIC_CAPTCHA_SESSION);

        if ($captchaSession === null) {
            return false;
        }

        return strtolower((string) $basicCaptchaValue) === strtolower((string) $captchaSession);
    }

    /**
     * {@inheritdoc}
     */
    public function shouldBreak(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }

    /**
     * {@inheritdoc}
     */
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
