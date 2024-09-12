<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
class ServiceRegistryClient
{
    public function __construct(
        private readonly string $registryUrl,
        private readonly HttpClientInterface $client,
    ) {
    }

    public function get(string $name): ServiceRegistryEntry
    {
        $services = $this->getAll();

        foreach ($services as $service) {
            if ($service->name === $name) {
                return $service;
            }
        }

        throw ServiceException::notFound('name', $name);
    }

    /**
     * @return array<ServiceRegistryEntry>
     */
    public function getAll(): array
    {
        try {
            $response = $this->client->request('GET', $this->registryUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            return array_map(
                static fn (array $service) => new ServiceRegistryEntry(
                    $service['name'],
                    $service['label'],
                    $service['host'],
                    $service['app-endpoint'],
                    (bool) ($service['activate-on-install'] ?? true),
                ),
                $response->toArray()
            );
        } catch (ExceptionInterface $e) {
            return [];
        }
    }
}
