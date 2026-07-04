<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderDefinition;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderEntity;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the OAuth2 client secret encrypted at rest and out of API responses.
 *
 * The admin UI sends the secret as a write-only field: a fresh plaintext value when the admin
 * types one, nothing otherwise. This subscriber:
 *   - encrypts a newly provided plaintext secret before it is persisted,
 *   - on edit without a new secret, re-injects the already-stored encrypted secret so overwriting
 *     the full `config` JSON does not wipe it, and
 *   - strips the (encrypted) secret from loaded entities so it never leaves via the Admin API.
 *
 * @internal
 */
#[Package('framework')]
class ProviderSecretSubscriber implements EventSubscriberInterface
{
    private const CONFIG_KEY = 'clientSecret';

    public function __construct(
        private readonly SecretEncryptor $secretEncryptor,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
            AdminAuthProviderDefinition::ENTITY_NAME . '.loaded' => 'stripSecret',
        ];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(AdminAuthProviderDefinition::ENTITY_NAME) as $command) {
            $payload = $command->getPayload();
            if (!isset($payload['config'])) {
                continue;
            }

            $config = json_decode((string) $payload['config'], true);
            if (!\is_array($config)) {
                continue;
            }

            $secret = $config[self::CONFIG_KEY] ?? null;

            if (\is_string($secret) && $secret !== '') {
                // A new plaintext secret was entered: store it encrypted.
                $config[self::CONFIG_KEY] = $this->secretEncryptor->encrypt($secret);
            } else {
                // No new secret: preserve whatever is already stored for this provider.
                unset($config[self::CONFIG_KEY]);
                $existing = $this->existingSecret($command->getPrimaryKey()['id'] ?? null);
                if ($existing !== null) {
                    $config[self::CONFIG_KEY] = $existing;
                }
            }

            $command->addPayload('config', json_encode($config, \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @param EntityLoadedEvent<AdminAuthProviderEntity> $event
     */
    public function stripSecret(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $provider) {
            $config = $provider->getConfig();
            if ($config === null || !\array_key_exists(self::CONFIG_KEY, $config)) {
                continue;
            }

            unset($config[self::CONFIG_KEY]);
            $provider->setConfig($config);
        }
    }

    private function existingSecret(?string $idBytes): ?string
    {
        if ($idBytes === null) {
            return null;
        }

        $config = $this->connection->fetchOne(
            'SELECT config FROM admin_auth_provider WHERE id = :id',
            ['id' => $idBytes]
        );

        if (!\is_string($config)) {
            return null;
        }

        $decoded = json_decode($config, true);

        return \is_array($decoded) && \is_string($decoded[self::CONFIG_KEY] ?? null)
            ? $decoded[self::CONFIG_KEY]
            : null;
    }
}
