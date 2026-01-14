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
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
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
        private readonly string $shopwareVersion,
    ) {
    }

    public function registerApp(Manifest $manifest, string $id, #[\SensitiveParameter] string $secretAccessKey, Context $context): void
    {
        if (!$manifest->getSetup()) {
            return;
        }

        $app = $this->fetchApp($id, $context);

        try {
            $appResponse = $this->registerWithApp($manifest, $app, $context);

            $secret = $appResponse['secret'];
            $confirmationUrl = $appResponse['confirmation_url'];

            if ($secret === $app->getAppSecret()) {
                throw AppException::registrationFailed(
                    $app->getName(),
                    'The new app secret returned from the App must be different from the current one.'
                );
            }

            // Sign confirmation with dual signatures for re-registration
            // shopware-shop-signature (new secret) + shopware-shop-signature-previous (current secret)
            $this->confirmRegistration($app, $context, $secret, $app->getAppSecret(), $secretAccessKey, $confirmationUrl);

            // After successful confirmation, save the new secret
            $this->saveAppSecret($app->getId(), $context, $secret);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $responseBody = $response->getBody()->getContents();
                $data = json_decode($responseBody, true);

                if (isset($data['error']) && \is_string($data['error'])) {
                    throw AppException::registrationFailed($app->getName(), $data['error']);
                }

                throw AppException::registrationFailed($app->getName(), \sprintf('Got status code %d, with response: %s', $response->getStatusCode(), $responseBody));
            }

            throw AppException::registrationFailed($app->getName(), $e->getMessage(), $e);
        } catch (GuzzleException $e) {
            throw AppException::registrationFailed($app->getName(), $e->getMessage(), $e);
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
        AppEntity $app,
        Context $context,
        #[\SensitiveParameter]
        string $secret,
        #[\SensitiveParameter]
        ?string $currentSecret,
        #[\SensitiveParameter]
        string $secretAccessKey,
        string $confirmationUrl
    ): void {
        $payload = $this->getConfirmationPayload($app, $secretAccessKey);

        $signature = $this->signPayload($payload, $secret);

        $headers = [
            'shopware-shop-signature' => $signature,
            'sw-version' => $this->shopwareVersion,
        ];

        // For re-registration, also send signature with current/old secret
        // shopware-shop-signature (new) + shopware-shop-signature-previous (current).
        // This is to ensure that only the party who initiated the re-registration can confirm it.
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
    private function getConfirmationPayload(AppEntity $app, #[\SensitiveParameter] string $secretAccessKey): array
    {
        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException $e) {
            throw AppRegistrationException::registrationFailed(
                $app->getName(),
                $e->getMessage(),
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
        return hash_hmac('sha256', json_encode($body, \JSON_THROW_ON_ERROR), $secret);
    }

    private function fetchApp(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->get($id);
        if (!$app instanceof AppEntity) {
            throw AppException::notFoundByField($id, 'id');
        }

        return $app;
    }
}
