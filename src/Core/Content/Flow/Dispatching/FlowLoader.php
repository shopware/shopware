<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal not intended for decoration or replacement
 */
class FlowLoader extends AbstractFlowLoader
{
    private Connection $connection;

    private LoggerInterface $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractFlowLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): array
    {
        $flows = $this->connection->fetchAllAssociative(
            'SELECT `event_name`, LOWER(HEX(`id`)) as `id`, `name`, `payload` FROM `flow`
                WHERE `active` = 1 AND `invalid` = 0 AND `payload` IS NOT NULL
                ORDER BY `priority` DESC',
        );

        if (empty($flows)) {
            return [];
        }

        foreach ($flows as $key => $flow) {
            try {
                $payload = unserialize($flow['payload']);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Flow payload is invalid:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n"
                );

                continue;
            }

            $flows[$key]['payload'] = $payload;
        }

        return FetchModeHelper::group($flows);
    }
}
