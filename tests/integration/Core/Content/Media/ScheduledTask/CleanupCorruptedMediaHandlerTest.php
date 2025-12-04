<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CleanupCorruptedMediaHandler::class)]
class CleanupCorruptedMediaHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private CleanupCorruptedMediaHandler $handler;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->handler = $this->getContainer()->get(CleanupCorruptedMediaHandler::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->mediaRepository->create([
            [
                'id' => $this->ids->create('corrupted-1'),
                'fileName' => 'corrupted-file-1.jpg',
                'fileSize' => null,
                'mediaType' => 'image/jpeg',
            ],
            [
                'id' => $this->ids->create('valid'),
                'fileName' => 'valid-file-1.jpg',
                'fileSize' => 2048,
                'mediaType' => 'image/jpeg',
            ],
            [
                'id' => $this->ids->create('corrupted-2'),
                'fileName' => 'corrupted-file-2.png',
                'fileSize' => null,
                'mediaType' => 'image/png',
            ],
        ], $this->context);
    }

    public function testCleanupCorruptedMedia(): void
    {
        $this->handler->run();

        $remainingMedia = $this->mediaRepository->searchIds(new Criteria([
            $this->ids->get('corrupted-1'),
            $this->ids->get('corrupted-2'),
            $this->ids->get('valid'),
        ]), $this->context);

        $remainingIds = $remainingMedia->getIds();
        static::assertCount(1, $remainingIds);
        static::assertSame($this->ids->get('valid'), $remainingIds[0]);
    }
}
