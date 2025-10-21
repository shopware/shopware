<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
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
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppRegistrationService
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly HandshakeFactory $handshakeFactory,
        private readonly Client $httpClient,
        private readonly EntityRepository $appRepository,
        private readonly string $shopUrl,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly string $shopwareVersion
    ) {
    }

    public function registerApp(Manifest $manifest, string $id, #[\SensitiveParameter] string $secretAccessKey, Context $context): void
    {
        if (!$manifest->getSetup()) {
            return;
        }

        try {
            $appName = $manifest->getMetadata()->getName();

            // Fetch existing app to support secret rotation with dual signatures
            $app = $this->getApp($id, $context);

            $appResponse = $this->registerWithApp($manifest, $app, $context);

            $secret = $appResponse['secret'];
            $confirmationUrl = $appResponse['confirmation_url'];

            // Sign confirmation with dual signatures for re-registration
            // shopware-shop-signature (new secret) + shopware-shop-signature-previous (current secret)
            $this->confirmRegistration($id, $context, $secret, $app->getAppSecret(), $secretAccessKey, $confirmationUrl);

            // After successful confirmation, save the new secret
            $this->saveAppSecret($id, $context, $secret);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['error']) && \is_string($data['error'])) {
                    throw AppException::registrationFailed($appName, $data['error']);
                }
            }

            throw AppException::registrationFailed($appName, $e->getMessage(), $e);
        } catch (GuzzleException $e) {
            throw AppException::registrationFailed($appName, $e->getMessage(), $e);
        }
    }

    /**
     * @throws GuzzleException
     *
     * @return array<string, string>
     */
    private function registerWithApp(Manifest $manifest, AppEntity $app, Context $context): array
    {
        $handshake = $this->handshakeFactory->create($manifest, $app);

        $request = $handshake->assembleRequest();
        $response = $this->httpClient->send($request, [AuthMiddleware::APP_REQUEST_CONTEXT => $context]);

        return $this->parseResponse($manifest->getMetadata()->getName(), $handshake, $response);
    }

    private function saveAppSecret(string $id, Context $context, #[\SensitiveParameter] string $secret): void
    {
        $update = ['id' => $id, 'appSecret' => $secret];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($update): void {
            $this->appRepository->update([$update], $context);
        });
    }

    private function confirmRegistration(
        string $id,
        Context $context,
        #[\SensitiveParameter]
        string $secret,
        ?string $currentSecret,
        #[\SensitiveParameter]
        string $secretAccessKey,
        string $confirmationUrl
    ): void {
        $payload = $this->getConfirmationPayload($id, $secretAccessKey, $context);

        $signature = $this->signPayload($payload, $secret);

        $headers = [
            'shopware-shop-signature' => $signature,
            'sw-version' => $this->shopwareVersion,
        ];

        // For re-registration, also send signature with current/old secret
        // shopware-shop-signature (new) + shopware-shop-signature-previous (current)
        if ($currentSecret !== null) {
            $previousSignature = $this->signPayload($payload, $currentSecret);
            $headers['shopware-shop-signature-previous'] = $previousSignature;
        }

        $this->httpClient->post($confirmationUrl, [
            'headers' => $headers,
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            'json' => $payload,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function parseResponse(
        string $appName,
        AppHandshakeInterface $handshake,
        ResponseInterface $response
    ): array {
        try {
            $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw AppException::registrationFailed($appName, 'JSON response could not be decoded', $e);
        }

        if (isset($data['error']) && \is_string($data['error'])) {
            throw AppException::registrationFailed($appName, $data['error']);
        }

        $proof = $data['proof'] ?? '';

        if (!\is_string($proof)) {
            throw AppException::registrationFailed($appName, 'The app server provided no proof');
        }

        if (!hash_equals($handshake->fetchAppProof(), trim($proof))) {
            throw AppException::registrationFailed($appName, 'The app server provided an invalid proof');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function getConfirmationPayload(string $id, #[\SensitiveParameter] string $secretAccessKey, Context $context): array
    {
        $app = $this->getApp($id, $context);

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
            throw AppRegistrationException::registrationFailed(
                $app->getName(),
                'The app url changed. Please resolve how the apps should handle this change.'
            );
        }

        // We can safely assume that the app has an integration because it is created together with the app
        // and explicitly fetched in the ::getApp() method below.
        $integration = $app->getIntegration();
        \assert($integration !== null);

        return [
            'apiKey' => $integration->getAccessKey(),
            'secretKey' => $secretAccessKey,
            'timestamp' => (string) (new \DateTime())->getTimestamp(),
            'shopUrl' => $this->shopUrl,
            'shopId' => $shopId,
        ];
    }

    /**
     * @param array<string, string> $body
     */
    private function signPayload(array $body, #[\SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', (string) json_encode($body, \JSON_THROW_ON_ERROR), $secret);
    }

    private function getApp(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        \assert($app !== null);

        return $app;
    }
}
