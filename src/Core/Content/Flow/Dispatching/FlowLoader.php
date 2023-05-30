<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('business-ops')]
class FlowLoader extends AbstractFlowLoader
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
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
