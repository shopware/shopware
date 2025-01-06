<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('storefront')]
readonly class CaptchaRouteListener implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param iterable<AbstractCaptcha> $captchas
     */
    public function __construct(
        private iterable $captchas,
        private SystemConfigService $systemConfigService,
        private ContainerInterface $container
    ) {
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
        /** @var bool $captchaAnnotation */
        $captchaAnnotation = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CAPTCHA, false);

        if ($captchaAnnotation === false) {
            return;
        }

        /** @var SalesChannelContext|null $context */
        $context = $event->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $salesChannelId = $context ? $context->getSalesChannelId() : null;

        $activeCaptchas = (array) ($this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $salesChannelId) ?? []);

        foreach ($this->captchas as $captcha) {
            $captchaConfig = $activeCaptchas[$captcha->getName()] ?? [];
            $request = $event->getRequest();
            if (
                $captcha->supports($request, $captchaConfig) && !$captcha->isValid($request, $captchaConfig)
            ) {
                if ($captcha->shouldBreak()) {
                    throw CaptchaException::invalid($captcha);
                }

                $violations = $captcha->getViolations();

                $event->setController(fn () => $this->container->get(ErrorController::class)->onCaptchaFailure($violations, $request));

                // Return on first invalid captcha
                return;
            }
        }
    }
}
