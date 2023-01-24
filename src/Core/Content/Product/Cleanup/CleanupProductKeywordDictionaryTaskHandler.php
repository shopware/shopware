<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @package inventory
 */
#[AsMessageHandler(handles: CleanupProductKeywordDictionaryTask::class)]

final class CleanupProductKeywordDictionaryTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        private readonly Connection $connection
    ) {
        parent::__construct($repository);
    }

    public function run(): void
    {
        $this->connection->executeStatement('DELETE FROM product_keyword_dictionary WHERE keyword NOT IN (SELECT DISTINCT keyword FROM product_search_keyword)');
    }
}
