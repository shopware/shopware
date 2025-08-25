<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class Client implements ResetInterface
{
    private readonly string $registryUrl;

    /**
     * @var array<ServiceEntry>
     */
    private ?array $services = null;

    public function __construct(
        string $registryUrl,
        private readonly HttpClientInterface $client,
    ) {
        $this->registryUrl = rtrim($registryUrl, '/');
    }

    public function get(string $name): ServiceEntry
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
     * @return array<ServiceEntry>
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
        } while ($page <= ($response['pagination']['pages'] ?? 1));

        $this->services = array_map(
            static fn (array $service) => new ServiceEntry(
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

    public function saveConsent(SaveConsentRequest $saveConsentRequest): void
    {
        try {
            $response = $this->client->request('POST', \sprintf('%s/api/consent/', $this->registryUrl), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($saveConsentRequest),
            ]);

            if ($response->getStatusCode() !== Response::HTTP_ACCEPTED) {
                throw ServiceException::consentSaveFailed('Unexpected response status code: ' . $response->getStatusCode());
            }
        } catch (ExceptionInterface $e) {
            throw ServiceException::consentSaveFailed($e->getMessage());
        }
    }

    public function revokeConsent(string $identifier): void
    {
        try {
            $response = $this->client->request('DELETE', \sprintf('%s/api/consent/revoke/%s', $this->registryUrl, $identifier), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== Response::HTTP_ACCEPTED) {
                throw ServiceException::consentRevokeFailed('Unexpected response status code: ' . $response->getStatusCode());
            }
        } catch (ExceptionInterface $e) {
            throw ServiceException::consentRevokeFailed($e->getMessage());
        }
    }

    /**
     * @return array<mixed>
     */
    private function fetchServices(int $page): ?array
    {
        try {
            $response = $this->client->request('GET', \sprintf('%s/api/service/', $this->registryUrl), [
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

            if (!$this->validateServicesResponse($content)) {
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
    private function validateServicesResponse(array $content): bool
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
