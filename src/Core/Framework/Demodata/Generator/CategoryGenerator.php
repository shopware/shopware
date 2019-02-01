<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Faker\Generator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\Uuid;

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
        $numberOfSubCategories = 40;

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'catalogId' => Defaults::CATALOG,
                'name' => $this->randomDepartment($context->getFaker()),
                'position' => $i,
            ];
        }

        foreach ($payload as $category) {
            for ($x = 0; $x < $numberOfSubCategories; ++$x) {
                $payload[] = [
                    'id' => Uuid::uuid4()->getHex(),
                    'catalogId' => Defaults::CATALOG,
                    'name' => $this->randomDepartment($context->getFaker()),
                    'parentId' => $category['id'],
                    'position' => $x,
                ];
            }
        }

        $console = $context->getConsole();
        $console->comment('Generated sub-categories: ' . $numberOfItems * $numberOfSubCategories);
        $console->progressStart($numberOfItems * $numberOfSubCategories);

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
                if (!\in_array($category, $categories)) {
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
        } while (\in_array($categoryName, $this->categories) && $unique);

        $this->categories[] = $categoryName;

        return $categoryName;
    }
}
