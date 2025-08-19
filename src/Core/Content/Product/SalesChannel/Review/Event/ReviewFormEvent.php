<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review\Event;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
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
            ->add(FlowMailVariables::REVIEW_FORM_DATA, new ObjectType())
            ->add(ProductAware::PRODUCT, new EntityType(ProductDefinition::class));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::REVIEW_FORM_DATA => $this->reviewFormData];
    }

    /**
     * @return array<string>
     */
    public static function getScalarKeys(): array
    {
        return [
            FlowMailVariables::REVIEW_FORM_DATA . '.forwardTo' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.parentId' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.forwardParameters' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.id' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.points' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.title' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.content' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.name' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.lastName' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.email' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.customerId' => '',
            FlowMailVariables::REVIEW_FORM_DATA . '.productId' => '',
        ];
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
