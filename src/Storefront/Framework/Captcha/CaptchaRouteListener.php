<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha as CaptchaAnnotation;
use Shopware\Storefront\Framework\Captcha\Exception\CaptchaInvalidException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CaptchaRouteListener implements EventSubscriberInterface
{
    /**
     * @var iterable|AbstractCaptcha[]
     */
    private $captchas;

    public function __construct(iterable $captchas)
    {
        $this->captchas = $captchas;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
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

        foreach ($this->captchas as $captcha) {
            if (
                $captcha->supports($event->getRequest()) && !$captcha->isValid($event->getRequest())
            ) {
                throw new CaptchaInvalidException($captcha);
            }
        }
    }
}
