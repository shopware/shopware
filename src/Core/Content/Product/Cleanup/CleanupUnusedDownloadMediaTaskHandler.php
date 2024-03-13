<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
#[Package('inventory')]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly UnusedMediaPurger $unusedMediaPurger,
        private readonly Connection $connection,
    ) {
        parent::__construct($repository, $logger);
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
        try {
            $this->connection->fetchOne('SELECT JSON_OVERLAPS(JSON_ARRAY(1), JSON_ARRAY(1));');
        } catch (\Exception $e) {
            // not supported, please update your database to MySQL 8.0 or MariaDB 10.9 or higher.
            return;
        }

        $this->unusedMediaPurger->deleteNotUsedMedia(
            null,
            null,
            null,
            ProductDownloadDefinition::ENTITY_NAME
        );
    }
}
