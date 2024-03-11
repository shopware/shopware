<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog\Gateway;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class DatadogGateway
{
    public function __construct(private readonly string $endpoint)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     */
    public function sendLogs(array $logs): void
    {
        if ($logs === []) {
            return;
        }

        $ch = \curl_init($this->endpoint);

        \assert($ch instanceof \CurlHandle);

        \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'POST');
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($logs, \JSON_THROW_ON_ERROR));
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'DD-API-KEY: ' . $_SERVER['DATADOG_API_KEY'],
        ]);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

        \curl_exec($ch);

        \curl_close($ch);
    }
}
