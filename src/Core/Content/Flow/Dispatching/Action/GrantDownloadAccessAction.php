<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\DelayAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class GrantDownloadAccessAction extends FlowAction
{
    private EntityRepositoryInterface $orderLineItemDownloadRepository;

    public function __construct(EntityRepositoryInterface $orderLineItemDownloadRepository)
    {
        $this->orderLineItemDownloadRepository = $orderLineItemDownloadRepository;
    }

    public static function getName(): string
    {
        return 'action.grant.download.access';
    }

    /**
     *  @deprecated tag:v6.5.0 Will be removed
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [OrderAware::class, DelayAware::class];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed
     */
    public function handle(FlowEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof CheckoutOrderPlacedEvent && !$baseEvent instanceof OrderStateMachineStateChangeEvent) {
            return;
        }

        $this->update($baseEvent->getContext(), $event->getConfig(), $baseEvent->getOrder());
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData(OrderAware::ORDER);

        $this->update($flow->getContext(), $flow->getConfig(), $order);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, OrderEntity $order): void
    {
        if (!isset($config['value'])) {
            return;
        }

        $lineItems = $order->getLineItems();

        if (!$lineItems) {
            return;
        }

        $downloadIds = [];

        foreach ($lineItems->filterGoodsFlat() as $lineItem) {
            $states = $lineItem->getStates();

            if (!$lineItem->getDownloads() || !\in_array(State::IS_DOWNLOAD, $states, true)) {
                continue;
            }

            /** @var OrderLineItemDownloadEntity $download */
            foreach ($lineItem->getDownloads() as $download) {
                $downloadIds[] = $download->getId();
                $download->setAccessGranted((bool) $config['value']);
            }
        }

        if (empty($downloadIds)) {
            return;
        }

        $this->orderLineItemDownloadRepository->update(
            array_map(function (string $id) use ($config): array {
                return ['id' => $id, 'accessGranted' => $config['value']];
            }, array_unique($downloadIds)),
            $context
        );
    }
}
