<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class ProductCategoryPathsSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, string>
     */
    private array $categoryIdCache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly SyncServiceInterface $syncService
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'categoryPathsToAssignment',
        ];
    }

    public function categoryPathsToAssignment(ImportExportBeforeImportRecordEvent $event): void
    {
        $row = $event->getRow();
        $entityName = $event->getConfig()->get('sourceEntity');

        if ($entityName !== ProductDefinition::ENTITY_NAME || empty($row['category_paths'])) {
            return;
        }

        $result = [];
        $categoriesPaths = explode('|', (string) $row['category_paths']);
        $newCategoriesPayload = [];

        foreach ($categoriesPaths as $path) {
            $categories = explode('>', $path);

            $categoryId = null;
            foreach ($categories as $currentIndex => $categoryName) {
                if (empty($categoryName)) {
                    continue;
                }

                $partialPath = implode('>', \array_slice($categories, 0, $currentIndex + 1));

                if (isset($this->categoryIdCache[$partialPath])) {
                    $categoryId = $this->categoryIdCache[$partialPath];

                    continue;
                }

                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('name', $categoryName));
                $criteria->addFilter(new EqualsFilter('parentId', $categoryId));

                $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();

                if ($category === null && $categoryId === null) {
                    break;
                }

                if ($category !== null) {
                    $categoryId = $category->getId();
                    $this->categoryIdCache[$partialPath] = $categoryId;

                    continue;
                }

                $parentId = $categoryId;
                $categoryId = Uuid::fromStringToHex($partialPath);
                $this->categoryIdCache[$partialPath] = $categoryId;

                $newCategoriesPayload[] = [
                    'id' => $categoryId,
                    'parent' => ['id' => $parentId],
                    'name' => $categoryName,
                ];
            }

            if ($categoryId !== null) {
                $result[] = ['id' => $categoryId];
            }
        }

        if (!empty($newCategoriesPayload)) {
            $this->createNewCategories($newCategoriesPayload);
        }

        $record = $event->getRecord();
        $record['categories'] = !empty($record['categories']) ? array_merge($record['categories'], $result) : $result;

        $event->setRecord($record);
    }

    public function reset(): void
    {
        $this->categoryIdCache = [];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function createNewCategories(array $payload): void
    {
        $this->syncService->sync([
            new SyncOperation(
                'write',
                CategoryDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $payload
            ),
        ], Context::createDefaultContext(), new SyncBehavior());
    }
}
