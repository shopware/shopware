<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
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
        private readonly AuthMiddleware $authMiddleware,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
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

    /**
     * @throws AppUrlChangeDetectedException
     */
    public function newAuthenticatedFor(ServiceRegistryEntry $entry, AppEntity $app, Context $context): AuthenticatedServiceClient
    {
        if (!$app->getAppSecret()) {
            throw ServiceException::missingAppSecretInfo($app->getId());
        }

        $stack = HandlerStack::create();
        $stack->push($this->authMiddleware);

        $authClient = new Client([
            'base_uri' => $entry->host,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => $app->getAppSecret(),
            ],
            'handler' => $stack,
        ]);

        return new AuthenticatedServiceClient(
            $authClient,
            $entry,
            $this->appPayloadServiceHelper->buildSource($app)
        );
    }

    public function fromName(string $name): ServiceClient
    {
        return $this->newFor(
            $this->serviceRegistryClient->get($name)
        );
    }
}
