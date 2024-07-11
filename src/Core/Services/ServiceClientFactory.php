<?php declare(strict_types=1);

namespace Shopware\Core\Services;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
class ServiceClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly string $shopwareVersion,
    ) {
    }

    public function newFor(ServiceRegistryEntry $entry): ServiceClient
    {
        return new ServiceClient(
            $this->client->withOptions([
                'base_uri' => $entry->host,
            ]),
            $this->shopwareVersion,
            $entry,
            new Filesystem()
        );
    }

    public function fromName(string $name): ServiceClient
    {
        $entry = $this->serviceRegistryClient->get($name);

        return new ServiceClient(
            $this->client->withOptions([
                'base_uri' => $entry->host,
            ]),
            $this->shopwareVersion,
            $entry,
            new Filesystem()
        );
    }
}
