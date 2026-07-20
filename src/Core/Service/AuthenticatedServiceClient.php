<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use GuzzleHttp\Client;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Commercial license syncing now uses the `commercial_license.provided` webhook. Can be removed when all services migrated.
 */
#[Package('framework')]
class AuthenticatedServiceClient
{
    public function __construct(
        public readonly Client $client,
        private readonly ServiceEntry $entry,
        private readonly Source $source,
    ) {
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (not triggering deprecation to avoid polluting logs)
     */
    public function syncLicense(string $licenseKey = '', string $licenseHost = ''): void
    {
        if ($this->entry->licenseSyncEndPoint === null) {
            return;
        }

        $payload = [
            'source' => $this->source->jsonSerialize(),
            'licenseKey' => $licenseKey,
            'licenseHost' => $licenseHost,
        ];

        try {
            $this->client->post($this->entry->licenseSyncEndPoint, ['json' => $payload]);
        } catch (\Throwable $exception) {
            throw ServiceException::requestTransportError($exception);
        }
    }
}
