<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Log;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;

/**
 * @internal
 */
#[Package('data-services')]
class DatabaseLog implements ConsentLogInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function log(ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void
    {
        $logEntry = [
            'consent-name' => $consentName,
            'action' => $action->value,
            'identifier' => $identifier,
            'actor' => $actor,
        ];

        $this->connection->insert('consent_log', [
            'consent_name' => $consentName,
            'timestamp' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'message' => \json_encode($logEntry),
        ]);
    }
}
