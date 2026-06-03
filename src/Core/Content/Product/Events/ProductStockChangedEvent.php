<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires for both producers of stock changes: order-driven deltas (StockStorage::alter,
 * carrying stockDelta) and direct product.stock writes (carrying the absolute stock).
 * The product entity is loaded lazily — only when a webhook payload is encoded. Flows
 * reload the product independently via ProductStorer using the id alone.
 */
#[Package('inventory')]
class ProductStockChangedEvent extends Event implements ProductAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'product.stock.changed';

    private ?ProductEntity $product = null;

    /**
     * @param \Closure(): ProductEntity $productLoader
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $productId,
        private readonly \Closure $productLoader,
        private readonly ?int $stock = null,
        private readonly ?int $stockDelta = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(ProductAware::PRODUCT, new EntityType(ProductDefinition::class))
            ->add(ProductAware::PRODUCT_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('stockChange', new ObjectType());
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product ??= ($this->productLoader)();
    }

    /**
     * Contains only the keys that are actually known for the producing path.
     *
     * @return array<string, int>
     */
    public function getStockChange(): array
    {
        $stockChange = [];

        if ($this->stock !== null) {
            $stockChange['stock'] = $this->stock;
        }

        if ($this->stockDelta !== null) {
            $stockChange['stockDelta'] = $this->stockDelta;
        }

        return $stockChange;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            ProductAware::PRODUCT_ID => $this->productId,
            'stockChange' => $this->getStockChange(),
        ];
    }
}
