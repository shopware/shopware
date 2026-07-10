<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DeliveryNoteRenderData;
use Shopware\Core\Checkout\Order\OrderEntity;
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

    final public const TEMPLATE_PATHS = [
        DocumentFormat::HTML->value => '@Framework/documents/delivery_note.html.twig',
    ];

    public function __construct(
        private DocumentConfigLoader $documentConfigLoader,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::DELIVERY_NOTE->value,
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
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): DeliveryNoteRenderData {
        $bundle = $this->documentConfigLoader->load(
            $generationRequest->documentType,
            $order->getSalesChannelId(),
            $context,
        );

        $documentNumber = $generationRequest->documentNumber;

        if ($documentNumber === null) {
            throw DocumentV2Exception::missingDocumentNumber($generationRequest->documentType);
        }

        return new DeliveryNoteRenderData(
            config: $bundle->config,
            company: $bundle->company,
            display: $bundle->display,
            documentDate: $generationRequest->documentDate,
            documentNumber: $documentNumber,
            documentComment: $generationRequest->documentComment,
            templatePaths: self::TEMPLATE_PATHS,
            custom: [
                'deliveryNoteNumber' => $documentNumber,
                'deliveryDate' => $generationRequest->documentDate,
                'deliveryNoteDate' => $generationRequest->documentDate,
            ],
            legacyConfig: $bundle->legacyConfig,
        );
    }
}
