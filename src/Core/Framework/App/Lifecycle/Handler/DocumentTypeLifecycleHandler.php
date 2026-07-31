<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeCollection;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class DocumentTypeLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppDocumentTypeCollection> $appDocumentTypeRepository
     */
    public function __construct(private readonly EntityRepository $appDocumentTypeRepository)
    {
    }

    public function install(AppPersistContext $context): void
    {
        $this->sync($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->sync($context);
    }

    private function sync(AppPersistContext $context): void
    {
        $appId = $context->app->getId();
        $existingDocumentTypes = $this->getExistingDocumentTypes($appId, $context->context);

        $documents = $context->manifest->getDocuments();
        $documentTypes = $documents !== null ? $documents->getDocumentTypes() : [];

        $upserts = [];

        foreach ($documentTypes as $documentType) {
            $payload = [
                'appId' => $appId,
                'technicalName' => $documentType->getIdentifier(),
                'config' => $documentType->getConfig() ?: null,
                'formats' => $documentType->getFormats(),
                'label' => $documentType->getLabel(),
            ];

            $existing = $existingDocumentTypes->filterByProperty(
                'technicalName',
                $documentType->getIdentifier()
            )->first();

            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingDocumentTypes->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->appDocumentTypeRepository->upsert($upserts, $context->context);
        }

        $this->deleteRemovedDocumentTypes($existingDocumentTypes, $context->context);
    }

    private function deleteRemovedDocumentTypes(AppDocumentTypeCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->appDocumentTypeRepository->delete($ids, $context);
        }
    }

    private function getExistingDocumentTypes(string $appId, Context $context): AppDocumentTypeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->appDocumentTypeRepository->search($criteria, $context)->getEntities();
    }
}
