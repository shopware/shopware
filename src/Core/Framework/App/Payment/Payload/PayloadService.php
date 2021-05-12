<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @internal only for use by the app-system
 */
class PayloadService
{
    protected ClientInterface $client;

    protected ShopIdProvider $shopIdProvider;

    private JsonEntityEncoder $entityEncoder;

    private DefinitionInstanceRegistry $definitionRegistry;

    private string $shopUrl;

    private string $shopwareVersion;

    public function __construct(
        JsonEntityEncoder $entityEncoder,
        DefinitionInstanceRegistry $definitionRegistry,
        ClientInterface $client,
        ShopIdProvider $shopIdProvider,
        string $shopUrl,
        string $shopwareVersion
    ) {
        $this->entityEncoder = $entityEncoder;
        $this->definitionRegistry = $definitionRegistry;
        $this->client = $client;
        $this->shopIdProvider = $shopIdProvider;
        $this->shopUrl = $shopUrl;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param class-string<AbstractResponse> $responseClass
     *
     * @throws ClientExceptionInterface
     */
    public function request(string $url, PaymentPayloadInterface $payload, AppEntity $app, string $responseClass): ?Struct
    {
        $request = $this->buildRequest(SymfonyRequest::METHOD_POST, $url, $payload, $app);

        $response = $this->client->sendRequest($request);
        $content = $response->getBody()->getContents();

        if (!$this->authenticateResponse($response, $content, $app, $payload->getOrderTransaction())) {
            return null;
        }

        return $responseClass::create($payload->getOrderTransaction()->getId(), json_decode($content, true));
    }

    private function buildRequest(string $method, string $url, PaymentPayloadInterface $payload, AppEntity $app): RequestInterface
    {
        $payload->setSource($this->buildSource($app));
        $encoded = $this->encode($payload);
        $jsonPayload = json_encode($encoded);

        if (!$jsonPayload) {
            throw new AsyncPaymentProcessException($payload->getOrderTransaction()->getId(), 'Invalid payload');
        }

        return new Request($method, $url, $this->getHeaders($jsonPayload, $app), $jsonPayload);
    }

    private function buildSource(AppEntity $app): Source
    {
        return new Source(
            $this->shopUrl,
            $this->shopIdProvider->getShopId(),
            $app->getVersion()
        );
    }

    private function authenticateResponse(ResponseInterface $response, string $content, AppEntity $app, OrderTransactionEntity $orderTransaction): bool
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            // should not happen, since payment methods are only added if secret is present
            throw new AsyncPaymentProcessException($orderTransaction->getId(), 'App secret missing');
        }

        $hmac = hash_hmac('sha256', $content, $secret);

        $signature = current($response->getHeader('shopware-app-signature'));

        if (empty($signature)) {
            return false;
        }

        return hash_equals($hmac, trim($signature));
    }

    private function getHeaders(string $jsonPayload, AppEntity $app): array
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw new AppRegistrationException('App secret missing');
        }

        $headers = [
            'Content-Type' => 'application/json',
            'shopware-shop-signature' => hash_hmac('sha256', $jsonPayload, $secret),
            'sw-version' => $this->shopwareVersion,
        ];

        return $headers;
    }

    private function encode(PaymentPayloadInterface $payload): array
    {
        $array = $payload->jsonSerialize();

        foreach ($array as $propertyName => $property) {
            if (!$property instanceof Entity) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($property->getApiAlias());

            $array[$propertyName] = $this->entityEncoder->encode(
                new Criteria(),
                $definition,
                $property,
                '/api'
            );
        }

        return $array;
    }
}
