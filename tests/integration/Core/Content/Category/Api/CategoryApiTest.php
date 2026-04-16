<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class CategoryApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

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

    public function testCreateCategoryWithMediaLinkType(): void
    {
        $context = Context::createDefaultContext();

        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            ['id' => $mediaId, 'name' => 'Test PDF'],
        ], $context);

        $categoryId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/category', [
            'id' => $categoryId,
            'name' => 'Media Link Category',
            'type' => CategoryDefinition::TYPE_LINK,
            'linkType' => CategoryDefinition::LINK_TYPE_MEDIA,
            'linkMediaId' => $mediaId,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, $context)->get($categoryId);

        static::assertNotNull($category);
        static::assertSame(CategoryDefinition::LINK_TYPE_MEDIA, $category->getLinkType());
        static::assertSame($mediaId, $category->getTranslation('linkMediaId'));
    }

    public function testCreateCategoryWithNonExistentMediaIdIsRejected(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/category', [
            'name' => 'Bad Media Link',
            'type' => CategoryDefinition::TYPE_LINK,
            'linkType' => CategoryDefinition::LINK_TYPE_MEDIA,
            'linkMediaId' => Uuid::randomHex(),
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        // Shopware surfaces FK constraint violations as HTTP 500 (DB error) rather than 400 (validation).
        // Once upstream adds explicit linkMediaId validation, this assertion should be tightened to assertSame(400, ...).
        static::assertGreaterThanOrEqual(400, $response->getStatusCode());
    }
}
