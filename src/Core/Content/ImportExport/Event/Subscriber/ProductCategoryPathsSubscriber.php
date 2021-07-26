<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductCategoryPathsSubscriber implements EventSubscriberInterface
{
    private EntityRepositoryInterface $categoryRepository;

    private SyncService $syncService;

    private array $categoryIdCache = [];

    public function __construct(EntityRepositoryInterface $categoryRepository, SyncService $syncService)
    {
        $this->categoryRepository = $categoryRepository;
        $this->syncService = $syncService;
    }

    public static function getSubscribedEvents()
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'categoryPathsToAssignment',
        ];
    }

    public function categoryPathsToAssignment(ImportExportBeforeImportRecordEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_8097')) {
            return;
        }

        $row = $event->getRow();
        $entityName = $event->getConfig()->get('sourceEntity');

        if ($entityName !== ProductDefinition::ENTITY_NAME || empty($row['category_paths'])) {
            return;
        }

        $result = [];
        $categoriesPaths = explode('|', $row['category_paths']);
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
            $this->createNewCategories($newCategoriesPayload, $row['category_paths']);
        }

        $record = $event->getRecord();
        $record['categories'] = !empty($record['categories']) ? array_merge($record['categories'], $result) : $result;

        $event->setRecord($record);
    }

    private function createNewCategories(array $payload, string $categoryPaths): void
    {
        if (Feature::isActive('FEATURE_NEXT_15815')) {
            $behavior = new SyncBehavior();
        } else {
            $behavior = new SyncBehavior(true, true);
        }

        $result = $this->syncService->sync([
            new SyncOperation(
                'write',
                CategoryDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $payload
            ),
        ], Context::createDefaultContext(), $behavior);

        if (Feature::isActive('FEATURE_NEXT_15815')) {
            // @internal (flag:FEATURE_NEXT_15815) - remove code below, "isSuccess" function will be removed, simply return because sync service would throw an exception in error case
            return;
        }

        if (!$result->isSuccess()) {
            $operation = $result->get('write');

            throw new ProcessingException(sprintf(
                'Failed writing categories for path %s with errors: %s',
                $categoryPaths,
                $operation ? json_encode(array_column($operation->getResult(), 'errors')) : ''
            ));
        }
    }
}
