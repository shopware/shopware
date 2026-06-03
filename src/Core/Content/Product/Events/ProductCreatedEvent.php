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
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires for every live product insert — admin, sync, import, and variant rows alike.
 * The product entity is loaded lazily, only when a webhook payload is encoded. Flows
 * reload the product independently via ProductStorer using the id alone.
 */
#[Package('inventory')]
class ProductCreatedEvent extends Event implements ProductAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'inventory.product.created';

    private ?ProductEntity $product = null;

    /**
     * @param \Closure(): ProductEntity $productLoader
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $productId,
        private readonly \Closure $productLoader
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
            ->add(ProductAware::PRODUCT_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
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
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            ProductAware::PRODUCT_ID => $this->productId,
        ];
    }
}
