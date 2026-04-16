<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class CategoryMediaLinkTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
    }

    public function testLinkMediaIdPersistsAndLoads(): void
    {
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            ['id' => $mediaId, 'name' => 'Test file'],
        ], $context);

        $categoryId = Uuid::randomHex();
        $this->categoryRepository->create([
            [
                'id' => $categoryId,
                'name' => 'Media Link Category',
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_MEDIA,
                'linkMediaId' => $mediaId,
            ],
        ], $context);

        $criteria = new Criteria([$categoryId]);
        $criteria->addAssociation('translations.linkMedia');

        $category = $this->categoryRepository->search($criteria, $context)->get($categoryId);

        static::assertNotNull($category);
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertSame(CategoryDefinition::LINK_TYPE_MEDIA, $category->getLinkType());
        static::assertSame($mediaId, $category->getTranslation('linkMediaId'));

        $linkMedia = $category->getLinkMedia();
        static::assertInstanceOf(MediaEntity::class, $linkMedia);
        static::assertSame($mediaId, $linkMedia->getId());
    }

    public function testLinkMediaIdIsNulledWhenMediaDeleted(): void
    {
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            ['id' => $mediaId, 'name' => 'Deletable file'],
        ], $context);

        $categoryId = Uuid::randomHex();
        $this->categoryRepository->create([
            [
                'id' => $categoryId,
                'name' => 'Media Link Category',
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_MEDIA,
                'linkMediaId' => $mediaId,
            ],
        ], $context);

        $this->mediaRepository->delete([['id' => $mediaId]], $context);

        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->get($categoryId);

        static::assertNotNull($category);
        static::assertNull($category->getTranslation('linkMediaId'));
    }
}
