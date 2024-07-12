<?php declare(strict_types=1);

namespace Shopware\Core\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
class ServiceRegistryClient
{
    public const SYSTEM_CONFIG_KEY_SERVICES_REGISTRY = 'core.services.registryUrl';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly SystemConfigService $systemConfigService,
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

        throw ServicesException::notFound('name', $name);
    }

    /**
     * @return array<ServiceRegistryEntry>
     */
    public function getAll(): array
    {
        try {
            $response = $this->client->request('GET', $this->systemConfigService->getString(static::SYSTEM_CONFIG_KEY_SERVICES_REGISTRY), [
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
                ),
                $response->toArray()
            );
        } catch (ExceptionInterface $e) {
            return [];
        }
    }
}
