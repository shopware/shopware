<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryBreadcrumbBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var string
     */
    private $deLanguageId;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();

        $salesChannel = $this->createSalesChannel([
            'navigationCategoryId' => $this->createTestData('navigation'),
            'serviceCategoryId' => $this->createTestData('service'),
            'footerCategoryId' => $this->createTestData('footer'),
        ]);
        $this->repository = $this->getContainer()->get('category.repository');

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', $salesChannel['id']);
    }

    /**
     * @dataProvider
     */
    public function breadcrumbDataProvider()
    {
        return [
            [
                'navigation',
                true,
                false,
            ],
            [
                'navigation',
                true,
                true,
            ],
            [
                'navigation',
                false,
                true,
            ],
            [
                'service',
                true,
                false,
            ],
            [
                'service',
                true,
                true,
            ],
            [
                'service',
                false,
                true,
            ],
            [
                'footer',
                true,
                false,
            ],
            [
                'footer',
                true,
                true,
            ],
            [
                'footer',
                false,
                true,
            ],
        ];
    }

    /**
     * @dataProvider breadcrumbDataProvider
     * @group slow
     */
    public function testIsWithoutEntrypoint(string $key, bool $withSalesChannel, bool $withCategoryId = false): void
    {
        $categoryId = $withCategoryId ? $this->ids->get($key) : null;
        $salesChannel = $withSalesChannel ? $this->salesChannelContext->getSalesChannel() : null;
        $builder = new CategoryBreadcrumbBuilder();

        /** @var CategoryCollection $categories */
        $categories = $this->repository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-1'));
        $category3 = $categories->get($this->ids->get($key . '-2'));

        $result1 = $builder->build($category1, $salesChannel, $categoryId);
        $result2 = $builder->build($category2, $salesChannel, $categoryId);
        $result3 = $builder->build($category3, $salesChannel, $categoryId);

        static::assertCount(0, $result1);
        static::assertSame(['EN-A'], array_values($result2));
        static::assertSame(['EN-A', 'EN-B'], array_values($result3));

        /** @var CategoryCollection $categories */
        $categories = $this->repository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-1'));
        $category3 = $categories->get($this->ids->get($key . '-2'));

        $result1 = $builder->build($category1, $salesChannel, $categoryId);
        $result2 = $builder->build($category2, $salesChannel, $categoryId);
        $result3 = $builder->build($category3, $salesChannel, $categoryId);

        static::assertCount(0, $result1);
        static::assertSame(['DE-A'], array_values($result2));
        static::assertSame(['DE-A', 'DE-B'], array_values($result3));
    }

    private function createTestData(string $key): string
    {
        $data = [
            [
                'id' => $this->ids->create($key),
                'translations' => [
                    ['name' => 'EN-Entry', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-Entry', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => $this->ids->create($key . '-1'),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-2'),
                                'translations' => [
                                    ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->getContainer()->get('category.repository')->create($data, $this->ids->getContext());

        return $this->ids->get($key);
    }
}
