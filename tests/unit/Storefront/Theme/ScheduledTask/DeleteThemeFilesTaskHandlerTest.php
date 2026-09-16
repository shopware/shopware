<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DeleteThemeFilesTaskHandler::class)]
class DeleteThemeFilesTaskHandlerTest extends TestCase
{
    public function testRunDelegatesToDeleter(): void
    {
        $unusedThemeFilesDeleter = $this->createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeFilesDeleter->expects($this->once())->method('deleteUnusedDirectories')->willReturn(0);

        $handler = new DeleteThemeFilesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $unusedThemeFilesDeleter
        );

        $handler->run();
    }
}
