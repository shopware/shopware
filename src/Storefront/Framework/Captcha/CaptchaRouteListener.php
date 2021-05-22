<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha as CaptchaAnnotation;
use Shopware\Storefront\Framework\Captcha\Exception\CaptchaInvalidException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CaptchaRouteListener implements EventSubscriberInterface
{
    /**
     * @var iterable<AbstractCaptcha>
     */
    private iterable $captchas;

    private ?ErrorController $errorController;

    private SystemConfigService $systemConfigService;

    public function __construct(
        iterable $captchas,
        ?ErrorController $errorController = null,
        SystemConfigService $systemConfigService
    ) {
        $this->captchas = $captchas;
        $this->errorController = $errorController;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['validateCaptcha', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
    }

    public function validateCaptcha(ControllerEvent $event): void
    {
        /** @var CaptchaAnnotation|bool $captchaAnnotation */
        $captchaAnnotation = $event->getRequest()->attributes->get('_captcha', false);

        if ($captchaAnnotation === false) {
            return;
        }

        if (!($captchaAnnotation instanceof CaptchaAnnotation)) {
            return;
        }

        $activeCaptchas = [];

        if (Feature::isActive('FEATURE_NEXT_12455')) {
            /** @var SalesChannelContext|null $context */
            $context = $event->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

            $salesChannelId = $context ? $context->getSalesChannelId() : null;

            $activeCaptchas = (array) ($this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $salesChannelId) ?? []);
        }

        foreach ($this->captchas as $captcha) {
            $captchaConfig = [];
            if (Feature::isActive('FEATURE_NEXT_12455')) {
                $captchaConfig = $activeCaptchas[$captcha->getName()] ?? [];
            }

            if (
                $captcha->supports($event->getRequest(), $captchaConfig) && !$captcha->isValid($event->getRequest(), $captchaConfig)
            ) {
                if (!Feature::isActive('FEATURE_NEXT_12455')) {
                    throw new CaptchaInvalidException($captcha);
                }

                if ($captcha->shouldBreak()) {
                    throw new CaptchaInvalidException($captcha);
                }

                $violations = $captcha->getViolations();

                /** @var ErrorController $errorController */
                $errorController = $this->errorController;

                $request = $event->getRequest();
                $event->setController(function () use (
                    $errorController,
                    $violations,
                    $request
                ) {
                    return $errorController->onCaptchaFailure($violations, $request);
                });
            }
        }
    }
}
