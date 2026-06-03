<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\ArrayType;
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
 * Fires once per live product update — admin, sync, import, and translation writes
 * alike. changedFields names the written fields (translated fields prefixed with
 * `translation.`); it is a delta hint, not a value diff.
 */
#[Package('inventory')]
class ProductUpdatedEvent extends Event implements ProductAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'inventory.product.updated';

    private ?ProductEntity $product = null;

    /**
     * @param \Closure(): ProductEntity $productLoader
     * @param list<string> $changedFields
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $productId,
        private readonly \Closure $productLoader,
        private readonly array $changedFields
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
            ->add('changedFields', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
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
     * @return list<string>
     */
    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            ProductAware::PRODUCT_ID => $this->productId,
            'changedFields' => $this->changedFields,
        ];
    }
}
