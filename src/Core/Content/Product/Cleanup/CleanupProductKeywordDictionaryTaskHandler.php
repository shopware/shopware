<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class CleanupProductKeywordDictionaryTaskHandler extends ScheduledTaskHandler
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        EntityRepositoryInterface $repository,
        Connection $connection
    ) {
        parent::__construct($repository);
        $this->connection = $connection;
    }

    public static function getHandledMessages(): iterable
    {
        return [CleanupProductKeywordDictionaryTask::class];
    }

    public function run(): void
    {
        $this->connection->executeStatement('DELETE FROM product_keyword_dictionary WHERE keyword NOT IN (SELECT DISTINCT keyword FROM product_search_keyword)');
    }
}
