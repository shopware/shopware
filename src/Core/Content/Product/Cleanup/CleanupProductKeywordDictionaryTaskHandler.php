<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class CleanupProductKeywordDictionaryTaskHandler extends ScheduledTaskHandler
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        Connection $connection
    ) {
        parent::__construct($repository);
        $this->connection = $connection;
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        yield CleanupProductKeywordDictionaryTask::class;
    }

    public function run(): void
    {
        $this->connection->executeStatement('DELETE FROM product_keyword_dictionary WHERE keyword NOT IN (SELECT DISTINCT keyword FROM product_search_keyword)');
    }
}
