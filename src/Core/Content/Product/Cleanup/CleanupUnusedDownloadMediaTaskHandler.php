<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Shopware\Core\Content\Media\DeleteNotUsedMediaService;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('inventory')]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $repository,
        private readonly DeleteNotUsedMediaService $deleteMediaService
    ) {
        parent::__construct($repository);
    }

    /**
     * @return string[]
     */
    public static function getHandledMessages(): iterable
    {
        return [CleanupUnusedDownloadMediaTask::class];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

        $context->addExtension(
            DeleteNotUsedMediaService::RESTRICT_DEFAULT_FOLDER_ENTITIES_EXTENSION,
            new ArrayStruct([ProductDownloadDefinition::ENTITY_NAME])
        );

        $this->deleteMediaService->deleteNotUsedMedia($context);
    }
}
