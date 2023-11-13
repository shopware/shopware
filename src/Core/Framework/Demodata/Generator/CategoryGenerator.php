<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class CategoryGenerator implements DemodataGeneratorInterface
{
    /**
     * @var list<string>
     */
    private array $categories = [];

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $cmsPageRepository,
        private readonly Connection $connection
    ) {
    }

    public function getDefinition(): string
    {
        return CategoryDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $rootCategoryId = $this->getRootCategoryId($context->getContext());
        $pageIds = $this->getCmsPageIds($context->getContext());
        $tags = $this->getIds('tag');

        $payload = [];
        $lastId = null;
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $cat = $this->createCategory($context, $pageIds, $tags, $rootCategoryId, $lastId, random_int(3, 5), 1);
            $payload[] = $cat;
            $lastId = $cat['id'];
        }

        $console = $context->getConsole();
        $console->progressStart($numberOfItems);

        foreach ($payload as $cat) {
            $this->categoryRepository->create([$cat], $context->getContext());

            $context->getConsole()->progressAdvance();
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @param list<string> $pageIds
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private function createCategory(DemodataContext $context, array $pageIds, array $tags, string $parentId, ?string $afterId, int $max, int $current): array
    {
        $id = Uuid::randomHex();

        $cat = [
            'id' => $id,
            'parentId' => $parentId,
            'afterCategoryId' => $afterId,
            'name' => $this->randomDepartment($context->getFaker()),
            'active' => true,
            'cmsPageId' => $context->getFaker()->randomElement($pageIds),
            'mediaId' => $context->getRandomId('media'),
            'description' => $context->getFaker()->text(),
            'tags' => $this->getTags($tags),
        ];

        if ($current >= $max) {
            return $cat;
        }

        $cat['children'] = $this->createCategories($context, $pageIds, $tags, random_int(2, 5), $id, $max, $current);

        return array_filter($cat);
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if (!empty($tags)) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit(), false);

            if (!empty($chosenTags)) {
                $tagAssignments = array_map(
                    fn ($id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return $tagAssignments;
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');
    }

    private function randomDepartment(Generator $faker, int $max = 3, bool $fixedAmount = false, bool $unique = true): string
    {
        if (!$fixedAmount) {
            $max = random_int(1, $max);
        }
        do {
            $categories = [];

            while (\count($categories) < $max) {
                $category = $faker->format('category');
                if (!\in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }

            if (\count($categories) >= 2) {
                $commaSeparatedCategories = implode(', ', \array_slice($categories, 0, -1));
                $categories = [
                    $commaSeparatedCategories,
                    end($categories),
                ];
            }
            ++$max;
            $categoryName = implode(' & ', $categories);
        } while (\in_array($categoryName, $this->categories, true) && $unique);

        $this->categories[] = $categoryName;

        return $categoryName;
    }

    private function getRootCategoryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        /** @var string $categoryId */
        $categoryId = $this->categoryRepository->searchIds($criteria, $context)->firstId();

        return $categoryId;
    }

    /**
     * @return list<string>
     */
    private function getCmsPageIds(Context $getContext): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', 'product_list'));
        $criteria->setLimit(500);

        /** @var list<string> $ids */
        $ids = $this->cmsPageRepository->searchIds($criteria, $getContext)->getIds();

        return $ids;
    }

    /**
     * @param list<string> $pageIds
     * @param list<string> $tags
     *
     * @return list<array<string, mixed>>
     */
    private function createCategories(DemodataContext $context, array $pageIds, array $tags, int $count, string $id, int $max, int $current): array
    {
        $children = [];
        $prev = null;

        for ($i = 1; $i <= $count; ++$i) {
            $child = $this->createCategory($context, $pageIds, $tags, $id, $prev, $max, $current + 1);
            $prev = $child['id'];
            $children[] = $child;
        }

        return $children;
    }
}
