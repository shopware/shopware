<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DeliveryNoteRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DeliveryNoteDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'delivery_note';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::DELIVERY_NOTE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociations([
            'currency',
            'language.locale',
            'addresses.country',
            'addresses.salutation',
            'addresses.countryState',
            'orderCustomer',
            'orderCustomer.customer',
            'lineItems',
            'deliveries.shippingMethod',
            'primaryOrderTransaction.paymentMethod',
        ]);

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        /** @deprecated tag:v6.8.0 - Remove when document templates use primaryOrderTransaction instead. */
        $criteria->getAssociation('transactions')
            ->addAssociations(['paymentMethod'])
            ->addSorting(new FieldSorting('createdAt'));
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): DeliveryNoteRenderData {
        $generationRequest = $input->generationRequest;
        $documentNumber = $generationRequest->documentNumber;

        if ($documentNumber === null) {
            throw DocumentV2Exception::missingDocumentNumber($generationRequest->documentType);
        }

        if ($generationRequest->deliveryDate === null) {
            throw DocumentV2Exception::missingDeliveryDate($generationRequest->documentType);
        }

        return new DeliveryNoteRenderData(
            custom: [
                'deliveryNoteNumber' => $documentNumber,
                'deliveryDate' => $generationRequest->deliveryDate,
                'deliveryNoteDate' => $generationRequest->documentDate,
            ],
        );
    }
}
