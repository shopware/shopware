<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package storefront
 *
 * @deprecated tag:v6.5.0 - reason:class-hierarchy-change - Won't extend AbstractMessageHandler anymore, message handling is done in `CacheWarmerTaskHandler`
 */
class CacheWarmer extends AbstractMessageHandler
{
    private EntityRepository $domainRepository;

    private MessageBusInterface $bus;

    private CacheRouteWarmerRegistry $registry;

    private CacheIdLoader $cacheIdLoader;

    private CacheWarmerTaskHandler $cacheWarmerTaskHandler;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $domainRepository,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry,
        CacheIdLoader $cacheIdLoader,
        CacheWarmerTaskHandler $cacheWarmerTaskHandler
    ) {
        $this->domainRepository = $domainRepository;
        $this->bus = $bus;
        $this->registry = $registry;
        $this->cacheIdLoader = $cacheIdLoader;
        $this->cacheWarmerTaskHandler = $cacheWarmerTaskHandler;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - will be removed, use `CacheWarmerTaskHandler` instead
     *
     * @return iterable<string>
     */
    public static function getHandledMessages(): iterable
    {
        return [];
    }

    public function warmUp(?string $cacheId = null): void
    {
        $cacheId = $cacheId ?? $this->cacheIdLoader->load();

        $criteria = new Criteria();
        $domains = $this->domainRepository->search($criteria, Context::createDefaultContext());

        $this->cacheIdLoader->write($cacheId);

        // generate all message to calculate message count
        $this->createMessages($cacheId, $domains);
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed, use `CacheWarmerTaskHandler` instead
     *
     * @param object $message
     */
    public function handle($message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', CacheWarmerTaskHandler::class)
        );

        if (!$message instanceof WarmUpMessage) {
            return;
        }

        $this->cacheWarmerTaskHandler->__invoke($message);
    }

    private function createMessages(string $cacheId, EntitySearchResult $domains): void
    {
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            foreach ($this->registry->getWarmers() as $warmer) {
                $message = $warmer->createMessage($domain, null);

                while ($message) {
                    $offset = $message->getOffset();

                    $message->setCacheId($cacheId);
                    $message->setDomain($domain->getUrl());

                    $this->bus->dispatch($message);

                    $message = $warmer->createMessage($domain, $offset);
                }
            }
        }
    }
}
