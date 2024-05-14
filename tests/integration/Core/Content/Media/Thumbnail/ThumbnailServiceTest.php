<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(ThumbnailService::class)]
class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGenerate(): void
    {
        $ids = new IdsCollection();

        $media = [
            'id' => $ids->get('media'),
            'fileName' => 'shopware-logo.png',
            'fileExtension' => 'png',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->getContainer()->get('media.repository')
            ->upsert([$media], Context::createDefaultContext());

        $media = $this->getContainer()->get('media.repository')
            ->search(new Criteria([$ids->get('media')]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $this->getFilesystem('shopware.filesystem.public')->writeStream($media->getPath(), $resource);

        $service = $this->getContainer()->get(ThumbnailService::class);
    }
}
