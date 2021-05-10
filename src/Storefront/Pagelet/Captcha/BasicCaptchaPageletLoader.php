<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Captcha;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha\AbstractBasicCaptchaGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class BasicCaptchaPageletLoader extends AbstractBasicCaptchaPageletLoader
{
    private EventDispatcherInterface $eventDispatcher;

    private AbstractBasicCaptchaGenerator $basicCaptchaGenerator;

    public function __construct(EventDispatcherInterface $eventDispatcher, AbstractBasicCaptchaGenerator $basicCaptchaGenerator)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->basicCaptchaGenerator = $basicCaptchaGenerator;
    }

    public function load(Request $request, SalesChannelContext $context): BasicCaptchaPagelet
    {
        $pagelet = new BasicCaptchaPagelet();
        $pagelet->setCaptcha($this->basicCaptchaGenerator->generate());

        $this->eventDispatcher->dispatch(
            new BasicCaptchaPageletLoadedEvent($pagelet, $context, $request)
        );

        return $pagelet;
    }
}
