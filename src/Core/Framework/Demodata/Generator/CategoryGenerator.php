<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Faker\Generator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryGenerator implements DemodataGeneratorInterface
{
    /**
     * @var string[]
     */
    private $categories = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(EntityRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getDefinition(): string
    {
        return CategoryDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $numberOfSubCategories = 0;

        $payload = [];
        $lastId = null;
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();

            $payload[] = [
                'id' => $id,
                'name' => $this->randomDepartment($context->getFaker()),
                'afterCategoryId' => $lastId,
            ];

            $lastId = $id;
        }

        foreach ($payload as $category) {
            $lastId = null;
            $randSubCategories = random_int(5, 12);
            $numberOfSubCategories += $randSubCategories;
            for ($x = 0; $x < $randSubCategories; ++$x) {
                $id = Uuid::randomHex();

                $payload[] = [
                    'id' => $id,
                    'name' => $this->randomDepartment($context->getFaker()),
                    'parentId' => $category['id'],
                    'afterCategoryId' => $lastId,
                ];

                $lastId = $id;
            }
        }

        $console = $context->getConsole();
        $console->comment('Generated sub-categories: ' . $numberOfSubCategories);
        $console->progressStart($numberOfItems + $numberOfSubCategories);

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->categoryRepository->upsert($chunk, $context->getContext());
            $context->getConsole()->progressAdvance(count($chunk));
        }

        $context->getConsole()->progressFinish();

        $context->add(CategoryDefinition::class, ...array_column($payload, 'id'));
    }

    private function randomDepartment(Generator $faker, int $max = 3, bool $fixedAmount = false, bool $unique = true)
    {
        if (!$fixedAmount) {
            $max = random_int(1, $max);
        }
        do {
            $categories = [];

            while (\count($categories) < $max) {
                $category = $faker->category;
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
}
