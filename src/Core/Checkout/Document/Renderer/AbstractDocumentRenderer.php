<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class AbstractDocumentRenderer
{
    abstract public function supports(): string;

    /**
     * @param DocumentGenerateOperation[] $operations
     *
     * @return RenderedDocument[]
     */
    abstract public function render(array $operations, Context $context, string $deepLinkCode = ''): array;

    abstract public function getDecorated(): AbstractDocumentRenderer;

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    protected function fetchOrders(
        EntityRepositoryInterface $orderRepository,
        array $operations,
        Criteria $criteria,
        Context $context,
        string $deepLinkCode = ''
    ): OrderCollection {
        $ids = [];

        foreach ($operations as $operation) {
            $ids[] = $operation->getOrderId();
        }

        $criteria->setIds($ids);

        $criteria->addAssociations([
            'lineItems',
            'transactions.paymentMethod',
            'currency',
            'language.locale',
            'addresses.country',
            'deliveries.positions',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'orderCustomer.customer',
        ]);

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        $criteria->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        /** @var OrderCollection $result */
        $result = $orderRepository->search($criteria, $context)->getEntities();

        return $result;
    }

    protected function getReferenceInvoice(Connection $connection, DocumentGenerateOperation $operation): array
    {
        $builder = $connection->createQueryBuilder();

        $builder->select([
            'LOWER(HEX(`document`.`id`)) as `id`',
            'LOWER(HEX(`document`.`order_version_id`)) as `order_version_id`',
            '`document`.`config` as `config`',
        ]);

        $builder
            ->from(DocumentDefinition::ENTITY_NAME)
            ->innerJoin(
                DocumentDefinition::ENTITY_NAME,
                DocumentTypeDefinition::ENTITY_NAME,
                DocumentTypeDefinition::ENTITY_NAME,
                '`document`.`document_type_id` = `document_type`.`id`'
            );

        $builder->where('`document_type`.`technical_name` = :techName')->andWhere('`document`.`order_id` = :orderId');
        $builder->setParameters([
            'techName' => InvoiceRenderer::TYPE,
            'orderId' => Uuid::fromHexToBytes($operation->getOrderId()),
        ]);

        $builder->orderBy('`document`.`created_at`', 'DESC');
        $builder->setMaxResults(1);

        $referenceDocumentId = $operation->getReferencedDocumentId();

        if ($referenceDocumentId !== null) {
            $builder->andWhere('`document`.`id` = :documentId');
            $builder->setParameter('documentId', Uuid::fromHexToBytes($referenceDocumentId));
        }

        $result = $builder->execute()->fetchAssociative();

        return $result !== false ? $result : [];
    }
}
