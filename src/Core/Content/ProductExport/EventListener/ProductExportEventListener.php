<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\EventListener;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductExportEventListener implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductExportCollection> $productExportRepository
     */
    public function __construct(
        private readonly EntityRepository $productExportRepository,
        private readonly ProductExportFileHandlerInterface $productExportFileHandler,
        private readonly FilesystemOperator $fileSystem
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_export.written' => 'afterWrite',
        ];
    }

    public function afterWrite(EntityWrittenEvent $event): void
    {
        $ids = [];
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            if (!$this->productExportWritten($writeResult)) {
                continue;
            }

            $primaryKey = $writeResult->getPrimaryKey();
            $ids[] = \is_array($primaryKey) ? $primaryKey['id'] : $primaryKey;
        }

        if ($ids === []) {
            return;
        }

        // reset and reload all written exports at once instead of one round-trip pair per export
        $this->productExportRepository->update(
            array_map(static fn (string $id): array => [
                'id' => $id,
                'generatedAt' => null,
                'nextGenerationAt' => null,
                // Reset stuck runs when a user/admin edits the export
                'isRunning' => false,
            ], $ids),
            $event->getContext()
        );

        $productExports = $this->productExportRepository->search(new Criteria($ids), $event->getContext())->getEntities();

        foreach ($productExports as $productExport) {
            $filePath = $this->productExportFileHandler->getFilePath($productExport);
            if ($this->fileSystem->fileExists($filePath)) {
                $this->fileSystem->delete($filePath);
            }
        }
    }

    private function productExportWritten(EntityWriteResult $writeResult): bool
    {
        return $writeResult->getEntityName() === ProductExportDefinition::ENTITY_NAME
            && !\array_key_exists('generatedAt', $writeResult->getPayload())
            && !\array_key_exists('nextGenerationAt', $writeResult->getPayload())
            && !\array_key_exists('isRunning', $writeResult->getPayload());
    }
}
