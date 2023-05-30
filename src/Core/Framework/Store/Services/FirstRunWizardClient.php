<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type Plugin array{
 *     id: int,
 *     name: string,
 *     label: string,
 *     iconPath: string,
 *     localizedInfo: array{name: string, shortDescription: string},
 *     producer: array{name: string},
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('merchant-services')]
class FirstRunWizardClient
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly AbstractStoreRequestOptionsProvider $optionsProvider,
        private readonly InstanceService $instanceService,
    ) {
    }

    /**
     * @return array{firstRunWizardUserToken: array{token: string, expirationDate: string}}
     */
    public function frwLogin(string $shopwareId, string $password, Context $context): array
    {
        if (!$context->getSource() instanceof AdminApiSource
            || $context->getSource()->getUserId() === null) {
            throw new \RuntimeException('First run wizard requires a logged in user');
        }

        $response = $this->client->request(
            Request::METHOD_POST,
            '/swplatform/firstrunwizard/login',
            [
                'json' => [
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                ],
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
            ]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{shopUserToken: array{token: string, expirationDate: string}, shopSecret: string}
     */
    public function upgradeAccessToken(Context $context): array
    {
        if (!$context->getSource() instanceof AdminApiSource
            || $context->getSource()->getUserId() === null) {
            throw new \RuntimeException('First run wizard requires a logged in user');
        }

        $response = $this->client->request(
            Request::METHOD_POST,
            '/swplatform/login/upgrade',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
                'json' => [
                    'shopwareUserId' => $context->getSource()->getUserId(),
                ],
            ]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return Plugin[]
     */
    public function getLanguagePlugins(Context $context): array
    {
        return $this->getPluginsFromStore('/swplatform/firstrunwizard/localizations', $context);
    }

    /**
     * @return Plugin[]
     */
    public function getDemoDataPlugins(Context $context): array
    {
        return $this->getPluginsFromStore('/swplatform/firstrunwizard/demodataplugins', $context);
    }

    /**
     * @return array<array{label: string, name: string, categories: array<array{name: string, label: string}>}>
     */
    public function getRecommendationRegions(Context $context): array
    {
        $response = $this->client->request(
            Request::METHOD_GET,
            '/swplatform/firstrunwizard/categories',
            ['query' => $this->optionsProvider->getDefaultQueryParameters($context)]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{
     *     id: int,
     *     iconPath: string,
     *     isCategoryLead: bool,
     *     localizedInfo: array{name: string, shortDescription: string},
     *     name: string,
     *     priority: int,
     *     producer: array{name: string}
     * }
     */
    public function getRecommendations(?string $region, ?string $category, Context $context): array
    {
        $query = [];
        $query['market'] = $region;
        $query['category'] = $category;

        $query = array_merge(
            $query,
            $this->optionsProvider->getDefaultQueryParameters($context)
        );

        $response = $this->client->request(
            Request::METHOD_GET,
            '/swplatform/firstrunwizard/plugins',
            ['query' => $query]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @throws GuzzleException
     * @throws \JsonException
     *
     * @return array<array{id: int, domain: string, verified?: bool, edition: array{name: string, label: string}}>
     */
    public function getLicenseDomains(Context $context): array
    {
        $response = $this->client->request(
            Request::METHOD_GET,
            '/swplatform/firstrunwizard/shops',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function checkVerificationSecret(string $domain, Context $context, bool $testEnvironment): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/swplatform/firstrunwizard/shops',
            [
                'json' => [
                    'domain' => $domain,
                    'shopwareVersion' => $this->instanceService->getShopwareVersion(),
                    'testEnvironment' => $testEnvironment,
                ],
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );
    }

    /**
     * @return array{content: string, fileName: string}
     */
    public function fetchVerificationInfo(string $domain, Context $context): array
    {
        $response = $this->client->request(
            Request::METHOD_POST,
            '/swplatform/firstrunwizard/shopdomainverificationhash',
            [
                'json' => ['domain' => $domain],
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return Plugin[]
     */
    private function getPluginsFromStore(string $endpoint, Context $context): array
    {
        $response = $this->client->request(
            Request::METHOD_GET,
            $endpoint,
            ['query' => $this->optionsProvider->getDefaultQueryParameters($context)]
        );

        return json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
