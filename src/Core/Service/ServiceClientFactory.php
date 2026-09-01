<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly string $shopwareVersion,
    ) {
    }

    public function newFor(ServiceEntry $entry): ServiceClient
    {
        return new ServiceClient(
            $this->client->withOptions([
                'base_uri' => $entry->host,
            ]),
            $this->shopwareVersion,
            $entry,
        );
    }

    public function fromName(string $name): ServiceClient
    {
        return $this->newFor(
            $this->serviceRegistryClient->get($name)
        );
    }
}
