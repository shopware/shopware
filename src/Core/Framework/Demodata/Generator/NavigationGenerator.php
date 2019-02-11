<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Navigation\NavigationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\Uuid;

class NavigationGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Generator
     */
    private $faker;

    public function __construct(
        EntityRepositoryInterface $repository,
        EntityRepositoryInterface $categoryRepository,
        Connection $connection
    ) {
        $this->repository = $repository;
        $this->categoryRepository = $categoryRepository;
        $this->connection = $connection;
    }

    public function getDefinition(): string
    {
        return NavigationDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $demodataContext, array $options = []): void
    {
        $this->faker = $demodataContext->getFaker();

        $pageIds = $this->connection->fetchAll('SELECT id FROM cms_page LIMIT 500');

        $pageIds = array_map(function ($id) {
            return Uuid::fromBytesToHex($id['id']);
        }, $pageIds);

        //we want only one navigation in the
        $navigationRootId = Uuid::uuid4()->getHex();

        //clear all navigation items
        $this->connection->executeUpdate('UPDATE sales_channel SET navigation_id = NULL');

        $this->connection->executeUpdate('DELETE FROM navigation');

        $root = ['id' => $navigationRootId, 'name' => 'Main navigation', 'cmsPageId' => $this->faker->randomElement($pageIds)];

        $context = Context::createDefaultContext();

        $navigations = $this->buildNavigationLevel($pageIds, $context, $navigationRootId);

        array_unshift($navigations, $root);

        $chunks = array_chunk($navigations, 50);

        $count = count($navigations);

        $demodataContext->getConsole()->section('Generating navigation tree');

        $demodataContext->getConsole()->progressStart($count);

        foreach ($chunks as $chunk) {
            $this->repository->create($chunk, $context);

            $demodataContext->getConsole()->progressAdvance(count($chunk));
        }

        $this->connection->executeUpdate(
            'UPDATE sales_channel SET navigation_id = :id, navigation_version_id = :version',
            ['id' => Uuid::fromHexToBytes($navigationRootId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );

        $demodataContext->getConsole()->progressFinish();
    }

    private function buildNavigationLevel(
        array $pageIds,
        Context $context,
        string $navigationParentId,
        string $categoryParentId = null
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', $categoryParentId));

        $categories = $this->categoryRepository->search($criteria, $context);

        $payload = [];
        foreach ($categories as $category) {
            $navigationId = Uuid::uuid4()->getHex();

            $navigation = [
                'id' => $navigationId,
                'parentId' => $navigationParentId,
                'categoryId' => $category->getId(),
                'name' => $category->getName(),
                'cmsPageId' => $this->faker->randomElement($pageIds),
            ];

            $payload[] = $navigation;

            $nested = $this->buildNavigationLevel($pageIds, $context, $navigationId, $category->getId());

            foreach ($nested as $item) {
                $payload[] = $item;
            }
        }

        return $payload;
    }
}
