<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @phpstan-type DocumentVariantOperation array{mediaId: string, title: string|null, changes?: array{title?: string|null, mediaId?: string|null}}
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ProductDocumentVariantController extends AbstractController
{
    /**
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     */
    public function __construct(private readonly EntityRepository $productDocumentRepository)
    {
    }

    #[Route(
        path: '/api/_action/product/{productId}/document-variants',
        name: 'api.action.product.document-variants',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:update']],
        methods: ['POST'],
    )]
    public function update(string $productId, Request $request, Context $context): JsonResponse
    {
        /** @var list<DocumentVariantOperation> $operations */
        $operations = $request->request->all('operations');
        $dryRun = $request->request->getBoolean('dryRun');
        $variantIds = [];

        foreach ($operations as $operation) {
            $criteria = new Criteria();
            $criteria->addFilter(
                new EqualsFilter('product.parentId', $productId),
                new EqualsFilter('mediaId', $operation['mediaId']),
                new EqualsFilter('title', $operation['title']),
            );
            $documents = $this->productDocumentRepository->search($criteria, $context)->getEntities();

            foreach ($documents as $document) {
                $variantIds[$document->getProductId()] = true;
            }

            if ($dryRun || $documents->count() === 0) {
                continue;
            }

            $ids = array_map(static fn (string $id) => ['id' => $id], array_values($documents->getIds()));
            $changes = isset($operation['changes']) ? array_intersect_key($operation['changes'], ['title' => true, 'mediaId' => true]) : null;

            if ($changes === null && $context->getLanguageId() === Defaults::LANGUAGE_SYSTEM) {
                $this->productDocumentRepository->delete($ids, $context);
                $this->refreshPositions(array_values(array_unique($documents->map(static fn (ProductDocumentEntity $document) => $document->getProductId()))), $context);

                continue;
            }

            $changes ??= ['title' => null, 'mediaId' => null];
            $this->productDocumentRepository->update(
                array_map(static fn (array $id) => [...$id, ...$changes], $ids),
                $context,
            );
        }

        return new JsonResponse(['variantIds' => array_keys($variantIds)]);
    }

    /**
     * @param list<string> $productIds
     */
    private function refreshPositions(array $productIds, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $criteria->addSorting(new FieldSorting('position'));

        $positions = [];
        $updates = [];
        foreach ($this->productDocumentRepository->search($criteria, $context)->getEntities() as $document) {
            $position = $positions[$document->getProductId()] ?? 0;
            $positions[$document->getProductId()] = $position + 1;

            if ($document->getPosition() !== $position) {
                $updates[] = ['id' => $document->getId(), 'position' => $position];
            }
        }

        if ($updates !== []) {
            $this->productDocumentRepository->update($updates, $context);
        }
    }
}
