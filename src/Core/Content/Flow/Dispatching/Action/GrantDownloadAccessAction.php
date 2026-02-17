<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class GrantDownloadAccessAction extends FlowAction implements DelayableAction
{
    /**
     * @param EntityRepository<OrderLineItemDownloadCollection> $orderLineItemDownloadRepository
     */
    public function __construct(private readonly EntityRepository $orderLineItemDownloadRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.grant.download.access';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
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
            $isDigital = Feature::isActive('v6.8.0.0')
                ? $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE) === ProductDefinition::TYPE_DIGITAL
                : (\in_array(State::IS_DOWNLOAD, $lineItem->getStates(), true)
                    || $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE) === ProductDefinition::TYPE_DIGITAL);

            if (!$lineItem->getDownloads() || !$isDigital) {
                continue;
            }

            foreach ($lineItem->getDownloads() as $download) {
                $downloadIds[] = $download->getId();
                $download->setAccessGranted((bool) $config['value']);
            }
        }

        if ($downloadIds === []) {
            return;
        }

        $this->orderLineItemDownloadRepository->update(
            array_map(fn (string $id): array => ['id' => $id, 'accessGranted' => $config['value']], array_unique($downloadIds)),
            $context
        );
    }
}
