<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('discovery')]
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
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $salesChannelId = $context ? $context->getSalesChannelId() : null;

        $activeCaptchas = (array) ($this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $salesChannelId) ?? []);
        $request = $event->getRequest();

        foreach ($this->captchas as $captcha) {
            $captchaConfig = $activeCaptchas[$captcha->getName()] ?? [];
            if (!$captcha->supports($request, $captchaConfig)) {
                continue;
            }

            $violations = $captcha->validate($request, $captchaConfig);
            if ($violations->count() === 0) {
                continue;
            }

            if ($captcha->shouldBreak() && !$request->isXmlHttpRequest()) {
                throw CaptchaException::invalid($captcha);
            }

            $event->setController(fn () => $this->container->get(ErrorController::class)->onCaptchaFailure($violations, $request));

            // Return on first invalid captcha
            return;
        }
    }
}
