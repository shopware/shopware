<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppRegistrationService
{
    /**
     * @var HandshakeFactory
     */
    private $handshakeFactory;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    public function __construct(
        HandshakeFactory $handshakeFactory,
        Client $httpClient,
        EntityRepositoryInterface $appRepository,
        string $shopUrl,
        ShopIdProvider $shopIdProvider
    ) {
        $this->handshakeFactory = $handshakeFactory;
        $this->httpClient = $httpClient;
        $this->appRepository = $appRepository;
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
    }

    public function registerApp(Manifest $manifest, string $id, string $secretAccessKey, Context $context): void
    {
        if (!$manifest->getSetup()) {
            return;
        }

        $appResponse = $this->registerWithApp($manifest);

        $secret = $appResponse['secret'];
        $confirmationUrl = $appResponse['confirmation_url'];

        $this->saveAppSecret($id, $context, $secret);

        $this->confirmRegistration($id, $context, $secret, $secretAccessKey, $confirmationUrl);
    }

    /**
     * @return array<string,string>
     */
    private function registerWithApp(Manifest $manifest): array
    {
        $handshake = $this->handshakeFactory->create($manifest);

        $request = $handshake->assembleRequest();

        try {
            $response = $this->httpClient->send($request);
        } catch (GuzzleException $e) {
            throw new AppRegistrationException($e->getMessage(), 0, $e);
        }

        return $this->parseResponse($handshake, $response);
    }

    private function saveAppSecret(string $id, Context $context, string $secret): void
    {
        $update = ['id' => $id, 'appSecret' => $secret];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($update): void {
            $this->appRepository->update([$update], $context);
        });
    }

    private function confirmRegistration(
        string $id,
        Context $context,
        string $secret,
        string $secretAccessKey,
        string $confirmationUrl
    ): void {
        $payload = $this->getConfirmationPayload($id, $secretAccessKey, $context);

        $signature = $this->signPayload($payload, $secret);

        try {
            $this->httpClient->post($confirmationUrl, [
                'headers' => [
                    'shopware-shop-signature' => $signature,
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new AppRegistrationException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string,string>
     */
    private function parseResponse(AppHandshakeInterface $handshake, ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->getContents(), true);

        $proof = $data['proof'] ?? '';

        if (!\is_string($proof)) {
            throw new AppRegistrationException('The app provided an invalid response');
        }

        if (!hash_equals($handshake->fetchAppProof(), trim($proof))) {
            throw new AppRegistrationException('The app provided an invalid response');
        }

        return $data;
    }

    /**
     * @return array<string,string>
     */
    private function getConfirmationPayload(string $id, string $secretAccessKey, Context $context): array
    {
        $app = $this->getApp($id, $context);

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            throw new AppRegistrationException(
                'The app url changed. Please resolve how the apps should handle this change.'
            );
        }

        return [
            'apiKey' => $app->getIntegration()->getAccessKey(),
            'secretKey' => $secretAccessKey,
            'timestamp' => (string) (new \DateTime())->getTimestamp(),
            'shopUrl' => $this->shopUrl,
            'shopId' => $shopId,
        ];
    }

    /**
     * @param array<string,string> $body
     */
    private function signPayload(array $body, string $secret): string
    {
        return hash_hmac('sha256', (string) json_encode($body), $secret);
    }

    private function getApp(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');

        /** @var AppEntity $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
    }
}
