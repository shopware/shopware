<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class InvoiceDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'invoice';

    public function __construct(
        private DocumentConfigLoader $documentConfigLoader,
        private ValidatorInterface $validator,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::INVOICE->value,
        ];
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociations([
            'currency',
            'language.locale',
            'addresses.country',
            'addresses.salutation',
            'addresses.countryState',
            'orderCustomer.customer',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'primaryOrderTransaction.paymentMethod',
            'primaryOrderDelivery.shippingMethod',
            'primaryOrderDelivery.shippingOrderAddress.country',
        ]);

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        /** @deprecated tag:v6.8.0 - Remove when document templates use primaryOrderTransaction instead. */
        $criteria->getAssociation('transactions')
            ->addAssociations(['paymentMethod'])
            ->addSorting(new FieldSorting('createdAt'));
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): InvoiceRenderData {
        $config = clone $this->documentConfigLoader->load(
            $generationRequest->documentType,
            $order->getSalesChannelId(),
            $context,
        );

        $isIntraCommunityDelivery = $this->isIntraCommunityDelivery(
            $config,
            $order,
        );

        $documentDate = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $config->merge([
            'documentDate' => $documentDate,
            'documentNumber' => $generationRequest->documentNumber,
            'intraCommunityDelivery' => $isIntraCommunityDelivery,
            'custom' => [
                'invoiceNumber' => $generationRequest->documentNumber,
            ],
        ]);

        return new InvoiceRenderData($config);
    }

    private function isIntraCommunityDelivery(DocumentConfiguration $config, OrderEntity $order): bool
    {
        $deliveryNote = $config->jsonSerialize()['displayAdditionalNoteDelivery'] ?? false;

        if ($deliveryNote === false) {
            return false;
        }

        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();

        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $orderDelivery = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderDelivery()
            : $order->getDeliveries()?->first();

        if ($orderDelivery === null) {
            return false;
        }

        $country = $orderDelivery->getShippingOrderAddress()?->getCountry();

        if ($country === null) {
            return false;
        }

        if (!$country->getCompanyTax()->getEnabled() || !$country->getIsEu()) {
            return false;
        }

        if ($country->getCheckVatIdPattern() === false) {
            return true;
        }

        $vatIds = $order->getOrderCustomer()?->getVatIds();

        if (!\is_array($vatIds)) {
            return false;
        }

        return $this->validator->validate(
            $vatIds,
            [
                new NotBlank(),
                new CustomerVatIdentification(
                    countryId: $country->getId(),
                ),
            ],
        )->count() === 0;
    }
}
