<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review\Event;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ProductStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
final class ReviewFormEvent extends Event implements SalesChannelAware, MailAware, ProductAware, CustomerAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'review_form.send';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $reviewFormData;

    public function __construct(
        private readonly Context $context,
        private readonly string $salesChannelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $reviewFormData,
        private readonly string $productId,
        private readonly string $customerId
    ) {
        $this->reviewFormData = $reviewFormData->all();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->merge(MailStorer::getStoreData())
            ->merge(TimezoneStorer::getStoreData())
            ->merge(ProductStorer::getStoreData())
            ->merge(CustomerStorer::getStoreData())
            ->add(
                FlowMailVariables::REVIEW_FORM_DATA,
                (new ObjectType())
                    ->add('forwardTo', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('parentId', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('forwardParameters', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('id', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('points', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('title', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('content', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('name', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('lastName', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('email', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('customerId', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('productId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            );
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::REVIEW_FORM_DATA => $this->reviewFormData];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getReviewFormData(): array
    {
        return $this->reviewFormData;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }
}
