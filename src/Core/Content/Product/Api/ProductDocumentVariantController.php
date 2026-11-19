<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
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
readonly class ProductDocumentVariantController
{
    /**
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     */
    public function __construct(
        private EntityRepository $productDocumentRepository,
        private Connection $connection,
    ) {
    }

    #[Route(
        path: '/api/_action/product/{productId}/document-variants',
        name: 'api.action.product.document-variants',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:update']],
        methods: ['POST'],
    )]
    public function sync(string $productId, Request $request, Context $context): JsonResponse
    {
        $operations = $this->getOperations($request);
        $dryRun = $request->request->getBoolean('dryRun');

        $variantIds = RetryableTransaction::retryable(
            $this->connection,
            fn (): array => $this->applyOperations($productId, $operations, $dryRun, $context),
        );

        return new JsonResponse(['variantIds' => array_keys($variantIds)]);
    }

    /**
     * @return list<DocumentVariantOperation>
     */
    private function getOperations(Request $request): array
    {
        $operations = array_values($request->request->all('operations'));

        foreach ($operations as $operation) {
            $isValid = \is_array($operation)
                && \is_string($operation['mediaId'] ?? null)
                && \array_key_exists('title', $operation)
                && ($operation['title'] === null || \is_string($operation['title']))
                && (!isset($operation['changes']) || \is_array($operation['changes']));

            if (!$isValid) {
                throw ProductException::invalidDocumentVariantOperations();
            }
        }

        /** @var list<DocumentVariantOperation> $operations */
        return $operations;
    }

    /**
     * @param list<DocumentVariantOperation> $operations
     *
     * @return array<string, true>
     */
    private function applyOperations(string $productId, array $operations, bool $dryRun, Context $context): array
    {
        $variantIds = [];

        foreach ($operations as $operation) {
            $criteria = new Criteria();
            $criteria->addFilter(
                new EqualsFilter('product.parentId', $productId),
                new EqualsFilter('mediaId', $operation['mediaId']),
                new EqualsFilter('title', $operation['title']),
            );
            $criteria->addFields(['productId', 'title']);
            $documents = $this->productDocumentRepository->search($criteria, $context)->getEntities()
                ->filter(static fn (Entity $document): bool => $document->getTranslation('title') === $operation['title']);

            foreach ($documents as $document) {
                $variantId = $document->get('productId');
                if (\is_string($variantId)) {
                    $variantIds[$variantId] = true;
                }
            }

            if ($dryRun || $documents->count() === 0) {
                continue;
            }

            $ids = array_map(static fn (string $id) => ['id' => $id], array_values($documents->getIds()));
            $changes = isset($operation['changes']) ? array_intersect_key($operation['changes'], ['title' => true, 'mediaId' => true]) : null;

            if ($changes === null && $context->getLanguageId() === Defaults::LANGUAGE_SYSTEM) {
                $this->productDocumentRepository->delete($ids, $context);

                continue;
            }

            $changes ??= ['title' => null, 'mediaId' => null];
            $this->productDocumentRepository->update(
                array_map(static fn (array $id) => [...$id, ...$changes], $ids),
                $context,
            );
        }

        return $variantIds;
    }
}
