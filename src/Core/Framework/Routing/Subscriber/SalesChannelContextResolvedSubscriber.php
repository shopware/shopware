<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Subscriber;

use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SalesChannelContextResolvedSubscriber implements EventSubscriberInterface
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var string
     */
    private $cacheDir;

    public function __construct(
        Environment $twig,
        string $cacheDir
    ) {
        $this->twig = $twig;
        $this->cacheDir = $cacheDir;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextResolvedEvent::class => [
                ['resolved'],
            ],
        ];
    }

    public function resolved(SalesChannelContextResolvedEvent $event): void
    {
        // Set individual saleschannel twig cache
        // TODO performance can be improved if indivudal cache keys are used instead (NEXT-4386)
        $this->twig->setCache(
            $this->cacheDir . '/'
            . $event->getSalesChannelContext()->getSalesChannel()->getId()
        );
    }
}
