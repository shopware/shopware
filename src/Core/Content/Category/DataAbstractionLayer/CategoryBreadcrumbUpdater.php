<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;

class CategoryBreadcrumbUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(Connection $connection, EntityRepositoryInterface $categoryRepository, EntityRepositoryInterface $languageRepository)
    {
        $this->connection = $connection;
        $this->categoryRepository = $categoryRepository;
        $this->languageRepository = $languageRepository;
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $query = $this->connection->createQueryBuilder();
        $query->select('category.path');
        $query->from('category');
        $query->where('category.id IN (:ids)');
        $query->andWhere('category.version_id = :version');
        $query->setParameter('version', $versionId);
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);

        $paths = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $all = $ids;
        foreach ($paths as $path) {
            $path = explode('|', (string) $path);
            foreach ($path as $id) {
                $all[] = $id;
            }
        }

        $all = array_filter(array_values(array_keys(array_flip($all))));

        $languages = $this->languageRepository->search(new Criteria(), $context);

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $context = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
                Defaults::LIVE_VERSION
            );

            $this->updateLanguage($ids, $context, $all);
        }
    }

    private function updateLanguage(array $ids, Context $context, array $all): void
    {
        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository
            ->search(new Criteria($all), $context)
            ->getEntities();

        $update = $this->connection->prepare('
            INSERT INTO `category_translation` (`category_id`, `category_version_id`, `language_id`, `breadcrumb`, `created_at`)
            VALUES (:categoryId, :versionId, :languageId, :breadcrumb, DATE(NOW()))
            ON DUPLICATE KEY UPDATE `breadcrumb` = :breadcrumb
        ');
        $update = new RetryableQuery($this->connection, $update);

        foreach ($ids as $id) {
            try {
                $path = $this->buildBreadcrumb($id, $categories);
            } catch (CategoryNotFoundException $e) {
                continue;
            }

            $update->execute([
                'categoryId' => Uuid::fromHexToBytes($id),
                'versionId' => $versionId,
                'languageId' => $languageId,
                'breadcrumb' => json_encode($path),
            ]);
        }
    }

    private function buildBreadcrumb(string $id, CategoryCollection $categories): array
    {
        $category = $categories->get($id);

        if (!$category) {
            throw new CategoryNotFoundException($id);
        }

        $breadcrumb = [];
        if ($category->getParentId()) {
            $breadcrumb = $this->buildBreadcrumb($category->getParentId(), $categories);
        }

        $breadcrumb[$category->getId()] = $category->getTranslation('name');

        return $breadcrumb;
    }
}
