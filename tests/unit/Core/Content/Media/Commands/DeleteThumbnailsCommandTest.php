<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\DeleteThumbnailsCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(DeleteThumbnailsCommand::class)]
class DeleteThumbnailsCommandTest extends TestCase
{
    public function testExecuteWithRemoteThumbnailsDisabled(): void
    {
        $command = new DeleteThumbnailsCommand(
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
            false
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        static::assertStringContainsStringIgnoringLineEndings('// Deleting thumbnails is only supported when remote thumbnail is enabled.', trim($commandTester->getDisplay()));
    }

    public function testExecuteWithRemoteThumbnailsEnabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $command = new DeleteThumbnailsCommand($connection, $thumbnailRepository, true);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        $thumbnailIds = [
            ['id' => Uuid::randomHex()],
            ['id' => Uuid::randomHex()],
        ];
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->with('SELECT LOWER(HEX(`id`)) as id FROM `media_thumbnail`')
            ->willReturn($thumbnailIds);

        $thumbnailRepository->expects(static::once())
            ->method('delete')
            ->with($thumbnailIds, static::isInstanceOf(Context::class));

        $connection->expects(static::once())
            ->method('executeStatement')
            ->with('UPDATE `media` SET `thumbnails_ro` = NULL;');
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        static::assertStringContainsString('Successfully deleted all thumbnails records and thumbnails files.', $commandTester->getDisplay());
    }
}
