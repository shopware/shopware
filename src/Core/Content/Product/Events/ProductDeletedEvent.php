<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Deliberately not ProductAware: the row is gone, so flow storers must not lazy-load
 * entity state from this event. The payload is a pre-delete snapshot.
 */
#[Package('inventory')]
class ProductDeletedEvent extends Event implements ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'inventory.product.deleted';

    public function __construct(
        private readonly Context $context,
        private readonly string $productId,
        private readonly string $deletedAt,
        private readonly ?string $productNumber = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('productId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('productNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deletedAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
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

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function getDeletedAt(): string
    {
        return $this->deletedAt;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'productId' => $this->productId,
            'productNumber' => $this->productNumber,
            'deletedAt' => $this->deletedAt,
        ];
    }
}
