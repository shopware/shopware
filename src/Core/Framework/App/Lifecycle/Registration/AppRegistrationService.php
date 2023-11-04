<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class AppRegistrationService
{
    public function __construct(
        private readonly HandshakeFactory $handshakeFactory,
        private readonly Client $httpClient,
        private readonly EntityRepository $appRepository,
        private readonly string $shopUrl,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly string $shopwareVersion
    ) {
    }

    public function registerApp(Manifest $manifest, string $id, string $secretAccessKey, Context $context): void
    {
        if (!$manifest->getSetup()) {
            return;
        }

        try {
            $appResponse = $this->registerWithApp($manifest, $context);

            $secret = $appResponse['secret'];
            $confirmationUrl = $appResponse['confirmation_url'];

            $this->saveAppSecret($id, $context, $secret);

            $this->confirmRegistration($id, $context, $secret, $secretAccessKey, $confirmationUrl);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['error']) && \is_string($data['error'])) {
                    throw new AppRegistrationException($data['error']);
                }
            }

            throw new AppRegistrationException($e->getMessage(), [], $e);
        } catch (GuzzleException $e) {
            throw new AppRegistrationException($e->getMessage(), [], $e);
        }
    }

    /**
     * @throws GuzzleException
     *
     * @return array<string, string>
     */
    private function registerWithApp(Manifest $manifest, Context $context): array
    {
        $handshake = $this->handshakeFactory->create($manifest);

        $request = $handshake->assembleRequest();
        $response = $this->httpClient->send($request, [AuthMiddleware::APP_REQUEST_CONTEXT => $context]);

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

        $this->httpClient->post($confirmationUrl, [
            'headers' => [
                'shopware-shop-signature' => $signature,
                'sw-version' => $this->shopwareVersion,
            ],
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            'json' => $payload,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function parseResponse(AppHandshakeInterface $handshake, ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        if (isset($data['error']) && \is_string($data['error'])) {
            throw new AppRegistrationException($data['error']);
        }

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
     * @return array<string, string>
     */
    private function getConfirmationPayload(string $id, string $secretAccessKey, Context $context): array
    {
        $app = $this->getApp($id, $context);

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
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
     * @param array<string, string> $body
     */
    private function signPayload(array $body, string $secret): string
    {
        return hash_hmac('sha256', (string) json_encode($body, \JSON_THROW_ON_ERROR), $secret);
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
