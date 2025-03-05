<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceRegistryClient implements ResetInterface
{
    /**
     * @var array<ServiceRegistryEntry>
     */
    private ?array $services = null;

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
        if ($this->services !== null) {
            return $this->services;
        }

        $rawServices = [];
        $page = 1;

        do {
            $response = $this->fetchServices($page);
            if ($response === null) {
                break;
            }

            $rawServices = array_merge($rawServices, $response['services']);
            ++$page;
        } while ($page <= ($response['pagination']['total-pages'] ?? 1));

        $this->services = array_map(
            static fn (array $service) => new ServiceRegistryEntry(
                $service['name'],
                $service['label'],
                $service['host'],
                $service['app-endpoint'],
                (bool) ($service['activate-on-install'] ?? true),
                $service['license-sync-endpoint'] ?? null
            ),
            $rawServices
        );

        return $this->services;
    }

    public function reset(): void
    {
        $this->services = null;
    }

    /**
     * @return array<mixed>
     */
    private function fetchServices(int $page): ?array
    {
        try {
            $response = $this->client->request('GET', $this->registryUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'page' => $page,
                    'limit' => 10,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->toArray();

            if (!$this->validateResponse($content)) {
                return null;
            }

            return $content;
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    /**
     * @param array<mixed> $content
     */
    private function validateResponse(array $content): bool
    {
        if (!isset($content['services'])) {
            return false;
        }

        foreach ($content['services'] as $service) {
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
