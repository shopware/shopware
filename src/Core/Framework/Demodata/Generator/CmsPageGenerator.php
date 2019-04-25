<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
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
     * @var string[]
     */
    private $productIds = [];

    /**
     * @var string[]
     */
    private $mediaIds = [];

    public function __construct(
        EntityRepositoryInterface $cmsPageRepository,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->productRepository = $productRepository;
        $this->mediaRepository = $mediaRepository;
    }

    public function getDefinition(): string
    {
        return CmsPageDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $pages = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $pages[] = $this->createPage($context);
        }

        $pages[] = $this->createProductDetailPage($context);

        $this->cmsPageRepository->upsert($pages, $context->getContext());
    }

    protected function createPage(DemodataContext $context): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $context->getFaker()->company,
            'type' => 'listing_page',
            'blocks' => [
                [
                    'type' => 'image-text',
                    'position' => 1,
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => $context->getFaker()->realText()]]],
                        ['type' => 'product-box', 'slot' => 'right', 'config' => ['product' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => $this->getRandomProductId($context)]]],
                    ],
                ],
                [
                    'type' => 'product-listing',
                    'position' => 2,
                    'slots' => [
                        [
                            'type' => 'product-listing',
                            'slot' => 'listing',
                            'config' => [],
                        ],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'position' => 3,
                    'slots' => [
                        ['type' => 'text', 'slot' => 'right', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => $context->getFaker()->realText()]]],
                        ['type' => 'image', 'slot' => 'left', 'config' => ['media' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => $this->getRandomMediaId($context)]]],
                    ],
                ],
            ],
        ];
    }

    private function createProductDetailPage(DemodataContext $context): array
    {
        return [
            'name' => 'Product detail page',
            'type' => 'product_detail',
            'blocks' => [
                [
                    'type' => 'image-text',
                    'position' => 1,
                    'slots' => [
                        [
                            'type' => 'text',
                            'slot' => 'left',
                            'config' => [
                                'content' => [
                                    'source' => FieldConfig::SOURCE_MAPPED,
                                    'value' => 'description',
                                ],
                            ],
                        ], [
                            'type' => 'image',
                            'slot' => 'right',
                            'config' => [
                                'media' => [
                                    'source' => FieldConfig::SOURCE_MAPPED,
                                    'value' => 'cover.media',
                                ],
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
}
