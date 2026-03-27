<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearch\Client;
use OpenSearch\EndpointFactoryInterface;
use OpenSearch\Namespaces\NamespaceBuilderInterface;
use OpenSearch\TransportInterface;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ConfiguredClient extends Client
{
    /**
     * @param array<NamespaceBuilderInterface> $configuredRegisteredNamespaces
     */
    public function __construct(
        private readonly TransportInterface $configuredTransport,
        private readonly EndpointFactoryInterface $configuredEndpointFactory,
        private readonly array $configuredRegisteredNamespaces,
        private readonly UriInterface $baseUri,
    ) {
        parent::__construct($configuredTransport, $configuredEndpointFactory, $configuredRegisteredNamespaces);
    }

    public function getConfiguredTransport(): TransportInterface
    {
        return $this->configuredTransport;
    }

    public function getConfiguredEndpointFactory(): EndpointFactoryInterface
    {
        return $this->configuredEndpointFactory;
    }

    /**
     * @return array<NamespaceBuilderInterface>
     */
    public function getConfiguredRegisteredNamespaces(): array
    {
        return $this->configuredRegisteredNamespaces;
    }

    public function getBaseUri(): UriInterface
    {
        return $this->baseUri;
    }
}
