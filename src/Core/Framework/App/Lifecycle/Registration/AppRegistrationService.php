<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
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
     * Upper bound on the unconfirmed-secret list. Each interrupted rotation or recovery prepends one mint;
     * without a cap the list would grow unbounded. An app realistically only holds its committed secret plus
     * the last mint or two, so keeping the newest few is enough for recovery to reconcile.
     */
    private const MAX_UNCONFIRMED_APP_SECRETS = 5;

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
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerApp(Manifest $manifest, string $id, #[\SensitiveParameter] string $secretAccessKey, Context $context): void
    {
        $this->register($manifest, $id, $secretAccessKey, $context, null);
    }

    /**
     * Re-register an app, signing with a specific secret we believe the app still holds instead of the
     * stored app_secret. A null candidate is a plain first-registration handshake for an app that never
     * registered.
     */
    public function reRegisterWithAppHeldSecret(
        Manifest $manifest,
        string $id,
        #[\SensitiveParameter]
        string $secretAccessKey,
        Context $context,
        #[\SensitiveParameter]
        ?string $appHeldSecret
    ): void {
        $this->register($manifest, $id, $secretAccessKey, $context, $appHeldSecret);
    }

    private function register(
        Manifest $manifest,
        string $id,
        #[\SensitiveParameter]
        string $secretAccessKey,
        Context $context,
        #[\SensitiveParameter]
        ?string $appHeldSecret
    ): void {
        if (!$manifest->getSetup()) {
            return;
        }

        $app = $this->fetchApp($id, $context);
        $currentSecret = $appHeldSecret ?? $app->getAppSecret();
        $logContext = ['appId' => $app->getId(), 'appName' => $app->getName()];

        try {
            $appResponse = $this->registerWithApp($manifest, $app, $context, $currentSecret);
        } catch (GuzzleException $e) {
            // The handshake failed before the app minted a new secret — any unconfirmed secret from an
            // earlier attempt stays untouched.
            throw $this->registrationFailedFromResponse($app, $e);
        }

        $secret = $appResponse['secret'];
        $confirmationUrl = $appResponse['confirmation_url'];

        if ($secret === $currentSecret) {
            throw AppException::registrationFailed(
                $app->getName(),
                'The new app secret returned from the App must be different from the current one.'
            );
        }

        // Build the payload first — a failure here (e.g. the shop id lookup) must not leave a false
        // unconfirmed record, since no confirm was ever sent.
        $confirmationPayload = $this->getConfirmationPayload($app, $secretAccessKey);

        // Save the minted secret as unconfirmed BEFORE the confirm leaves. If we crash between confirm and
        // commit, this is the only record of a secret the app may already hold — what recovery re-registers
        // against.
        $this->saveUnconfirmedAppSecrets($app->getId(), $context, $secret);

        try {
            // A re-registration confirm carries two signatures: shopware-shop-signature signed with the new
            // secret (proves we received it) and shopware-shop-signature-previous signed with the current
            // secret (proves we are the same shop the app already knows).
            $this->confirmRegistration($context, $secret, $currentSecret, $confirmationPayload, $confirmationUrl);
        } catch (ClientException $e) {
            // A 4xx means the app answered and clearly rejected the confirm. Drop the rejected secret so
            // app_secret stays on the current (old) value; both sides now agree on the old secret.
            $this->dropRejectedAppSecret($app->getId(), $context, $secret);

            $this->logger->warning('App secret rotation rejected by app, kept current secret', $logContext);

            throw $this->registrationFailedFromResponse($app, $e);
        } catch (GuzzleException $e) {
            // Any other failure (5xx, timeout) is unclear: the app may have switched to the new secret before
            // it failed. Keep the unconfirmed secret so recovery can try it later.
            $this->logger->warning('App secret rotation outcome unknown, unconfirmed secret retained', $logContext);

            throw $this->registrationFailedFromResponse($app, $e);
        }

        // Confirmed (2xx): set app_secret to the new value and clear the unconfirmed secret.
        $this->commitAppSecret($app->getId(), $context, $secret);

        $this->logger->info('App secret committed after confirmation', $logContext);
    }

    private function registrationFailedFromResponse(AppEntity $app, GuzzleException $e): AppException
    {
        if ($e instanceof RequestException && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            $reason = isset($data['error']) && \is_string($data['error'])
                ? $data['error']
                : \sprintf('Got status code %d, with response: %s', $response->getStatusCode(), $responseBody);

            // A 4xx is a clear rejection (e.g. a signature the app does not trust) — a distinct type so
            // recovery can tell a wrong secret ("try the next candidate") from an unknown outcome ("retry").
            if ($e instanceof ClientException) {
                return AppException::appRegistrationRejected($app->getName(), $reason, $e);
            }

            return AppException::registrationFailed($app->getName(), $reason, $e);
        }

        return AppException::registrationFailed($app->getName(), $e->getMessage(), $e);
    }

    /**
     * @throws GuzzleException
     *
     * @return array<string, string>
     */
    private function registerWithApp(Manifest $manifest, AppEntity $app, Context $context, #[\SensitiveParameter] ?string $currentSecret): array
    {
        $handshake = $this->handshakeFactory->create($manifest, $app, $currentSecret);

        $request = $handshake->assembleRequest();
        $response = $this->httpClient->send($request, [AuthMiddleware::APP_REQUEST_CONTEXT => $context]);

        return $this->parseResponse($manifest->getMetadata()->getName(), $handshake, $response);
    }

    private function saveUnconfirmedAppSecrets(string $id, Context $context, #[\SensitiveParameter] string $secret): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id, $secret): void {
            // Prepend, most-recent first: a recovery adds its new mint ahead of the ones it is still trying.
            $unconfirmed = $this->fetchApp($id, $context)->getUnconfirmedAppSecrets() ?? [];
            $unconfirmed = array_values(array_unique(array_merge([$secret], $unconfirmed)));
            if (\count($unconfirmed) > self::MAX_UNCONFIRMED_APP_SECRETS) {
                // Evict the newest of the old mints, never the tail: in a stuck-ambiguous loop the oldest
                // entry is the secret the app most likely holds, so it must survive the cap.
                array_splice(
                    $unconfirmed,
                    self::MAX_UNCONFIRMED_APP_SECRETS - 1,
                    \count($unconfirmed) - self::MAX_UNCONFIRMED_APP_SECRETS
                );
            }

            $this->appRepository->update([['id' => $id, 'unconfirmedAppSecrets' => $unconfirmed]], $context);
        });
    }

    private function commitAppSecret(string $id, Context $context, #[\SensitiveParameter] string $secret): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id, $secret): void {
            $this->appRepository->update([['id' => $id, 'appSecret' => $secret, 'unconfirmedAppSecrets' => null]], $context);
        });
    }

    private function dropRejectedAppSecret(string $id, Context $context, #[\SensitiveParameter] string $rejectedSecret): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id, $rejectedSecret): void {
            // A definitive 4xx rejected exactly this secret; remove it by value and keep any others a recovery
            // is still trying. An empty list collapses to null so "has an unconfirmed" stays a null check.
            $unconfirmed = array_values(array_filter(
                $this->fetchApp($id, $context)->getUnconfirmedAppSecrets() ?? [],
                static fn (string $secret): bool => $secret !== $rejectedSecret
            ));

            $this->appRepository->update([[
                'id' => $id,
                'unconfirmedAppSecrets' => $unconfirmed === [] ? null : $unconfirmed,
            ]], $context);
        });
    }

    /**
     * @param array<string, string> $payload
     */
    private function confirmRegistration(
        Context $context,
        #[\SensitiveParameter]
        string $secret,
        #[\SensitiveParameter]
        ?string $currentSecret,
        #[\SensitiveParameter]
        array $payload,
        string $confirmationUrl
    ): void {
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
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
            'shopUrl' => $this->shopUrl,
            'shopId' => $shopId->id,
        ];
    }

    /**
     * @param array<string, string> $body
     */
    private function signPayload(#[\SensitiveParameter] array $body, #[\SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', json_encode($body, \JSON_THROW_ON_ERROR), $secret);
    }

    private function fetchApp(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->get($id);
        if (!$app instanceof AppEntity) {
            throw AppException::notFoundByField($id, 'id');
        }

        return $app;
    }
}
