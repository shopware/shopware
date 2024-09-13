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

            $content = $response->toArray();

            if (!$this->validateResponse($content)) {
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

    /**
     * @param array<mixed> $content
     */
    private function validateResponse(array $content): bool
    {
        foreach ($content as $service) {
            if (!\is_array($service)) {
                return false;
            }

            if (!isset($service['name']) || !isset($service['label']) || !isset($service['host']) || !isset($service['app-endpoint'])) {
                return false;
            }
        }

        return true;
    }
}
