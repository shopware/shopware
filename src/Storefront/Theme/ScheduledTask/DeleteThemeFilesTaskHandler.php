<?php

declare(strict_types=1);

namespace Shopware\Storefront\Theme\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: DeleteThemeFilesTask::class)]
final class DeleteThemeFilesTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->unusedThemeDirectoryDeleter->deleteUnusedDirectories();
    }
}
