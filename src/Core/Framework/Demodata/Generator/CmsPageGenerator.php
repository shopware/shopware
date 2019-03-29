<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class CmsPageGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var string[]
     */
    private $productIds = [];

    /**
     * @var string[]
     */
    private $mediaIds = [];

    /**
     * @var string[]
     */
    private $categoryIds;

    public function __construct(
        EntityRepositoryInterface $cmsPageRepository,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $categoryRepository
    ) {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->productRepository = $productRepository;
        $this->mediaRepository = $mediaRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public function getDefinition(): string
    {
        return CmsPageDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $pages = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $pages[] = random_int(0, 1) ? $this->createLandingPage($context) : $this->createListingPage($context);
        }

        $this->cmsPageRepository->upsert($pages, $context->getContext());
    }

    protected function createLandingPage(DemodataContext $context): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $context->getFaker()->company,
            'type' => 'landing_page',
            'blocks' => [
                [
                    'type' => 'image-text',
                    'position' => 1,
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => $context->getFaker()->realText()]],
                        ['type' => 'product-box', 'slot' => 'right', 'config' => ['productId' => $this->getRandomProductId($context)]],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'position' => 2,
                    'slots' => [
                        ['type' => 'text', 'slot' => 'right', 'config' => ['content' => $context->getFaker()->realText()]],
                        ['type' => 'image', 'slot' => 'left', 'config' => ['mediaId' => $this->getRandomMediaId($context)]],
                    ],
                ],
            ],
        ];
    }

    protected function createListingPage(DemodataContext $context): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $context->getFaker()->company,
            'type' => 'listing_page',
            'blocks' => [
                [
                    'type' => 'product-listing',
                    'position' => 1,
                    'slots' => [
                        [
                            'type' => 'product-listing',
                            'slot' => 'listing',
                            'config' => [
                                'categoryId' => $this->getRandomCategoryId($context),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getRandomProductId(DemodataContext $context): string
    {
        if ($productId = $context->getRandomId(ProductDefinition::class)) {
            return $productId;
        }

        if (empty($this->productIds)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->productIds = $this->productRepository->searchIds($criteria, $context->getContext())->getIds();
        }

        return $this->productIds[array_rand($this->productIds, 1)];
    }

    private function getRandomMediaId(DemodataContext $context): string
    {
        if ($mediaId = $context->getRandomId(MediaDefinition::class)) {
            return $mediaId;
        }

        if (empty($this->mediaIds)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->mediaIds = $this->mediaRepository->searchIds($criteria, $context->getContext())->getIds();
        }

        return $this->mediaIds[array_rand($this->mediaIds, 1)];
    }

    private function getRandomCategoryId(DemodataContext $context): string
    {
        if ($categoryId = $context->getRandomId(CategoryDefinition::class)) {
            return $categoryId;
        }

        if (empty($this->categoryIds)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->categoryIds = $this->categoryRepository->searchIds($criteria, $context->getContext())->getIds();
        }

        return $this->categoryIds[array_rand($this->categoryIds, 1)];
    }
}
