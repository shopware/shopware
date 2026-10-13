<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\ScheduledTask\CleanupPresignedUploadsHandler;
use Shopware\Core\Content\Media\Upload\PresignedUrlGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CleanupPresignedUploadsHandler::class)]
class CleanupPresignedUploadsHandlerTest extends TestCase
{
    public function testRunDeletesAbandonedStagingUploads(): void
    {
        $presignedUrlGenerator = $this->createMock(PresignedUrlGeneratorInterface::class);
        $presignedUrlGenerator->method('isSupported')->willReturn(true);
        $presignedUrlGenerator->expects($this->once())->method('deleteAbandonedStagingUploads');

        $this->createHandler($presignedUrlGenerator)->run();
    }

    public function testRunSkipsTheCleanupWhenPresignedUploadsAreNotSupported(): void
    {
        $presignedUrlGenerator = $this->createMock(PresignedUrlGeneratorInterface::class);
        $presignedUrlGenerator->method('isSupported')->willReturn(false);
        $presignedUrlGenerator->expects($this->never())->method('deleteAbandonedStagingUploads');

        $this->createHandler($presignedUrlGenerator)->run();
    }

    private function createHandler(PresignedUrlGeneratorInterface $presignedUrlGenerator): CleanupPresignedUploadsHandler
    {
        return new CleanupPresignedUploadsHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            new NullLogger(),
            $presignedUrlGenerator,
        );
    }
}
