<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderDefinition;
use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderEntity;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\AdminAuth\Subscriber\ProviderSecretSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProviderSecretSubscriber::class)]
class ProviderSecretSubscriberTest extends TestCase
{
    private SecretEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor('test-app-secret');
    }

    public function testSubscribesToProviderWritesAndLoads(): void
    {
        static::assertSame([
            EntityWriteEvent::class => 'beforeWrite',
            'admin_auth_provider.loaded' => 'stripSecret',
        ], ProviderSecretSubscriber::getSubscribedEvents());
    }

    public function testBeforeWriteEncryptsANewPlaintextSecret(): void
    {
        $command = $this->command([
            'config' => json_encode(['clientId' => 'client-id', 'clientSecret' => 'plain-secret'], \JSON_THROW_ON_ERROR),
        ]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('config', static::callback(function (string $configJson): bool {
                $config = json_decode($configJson, true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($config);
                static::assertSame('client-id', $config['clientId']);
                static::assertIsString($config['clientSecret']);
                static::assertNotSame('plain-secret', $config['clientSecret'], 'the secret must not be stored in plaintext');
                static::assertSame('plain-secret', $this->encryptor->decrypt($config['clientSecret']));

                return true;
            }));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $this->createSubscriber($connection)->beforeWrite($this->writeEvent($command));
    }

    public function testBeforeWriteReInjectsTheStoredSecretWhenNoneIsProvided(): void
    {
        $idBytes = Uuid::randomBytes();
        $storedSecret = $this->encryptor->encrypt('old-secret');

        // The admin UI sends an empty write-only secret when the admin did not type a new one.
        $command = $this->command(
            ['config' => json_encode(['clientId' => 'client-id', 'clientSecret' => ''], \JSON_THROW_ON_ERROR)],
            $idBytes
        );
        $command->expects($this->once())
            ->method('addPayload')
            ->with('config', json_encode(['clientId' => 'client-id', 'clientSecret' => $storedSecret], \JSON_THROW_ON_ERROR));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(static::anything(), ['id' => $idBytes])
            ->willReturn(json_encode(['clientId' => 'client-id', 'clientSecret' => $storedSecret], \JSON_THROW_ON_ERROR));

        $this->createSubscriber($connection)->beforeWrite($this->writeEvent($command));
    }

    public function testBeforeWriteDropsTheSecretKeyWhenNothingIsStored(): void
    {
        $command = $this->command(
            ['config' => json_encode(['clientId' => 'client-id'], \JSON_THROW_ON_ERROR)],
            Uuid::randomBytes()
        );
        $command->expects($this->once())
            ->method('addPayload')
            ->with('config', json_encode(['clientId' => 'client-id'], \JSON_THROW_ON_ERROR));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $this->createSubscriber($connection)->beforeWrite($this->writeEvent($command));
    }

    public function testBeforeWriteIgnoresCommandsWithoutAConfigPayload(): void
    {
        $command = $this->command(['label' => 'Corporate SSO']);
        $command->expects($this->never())->method('addPayload');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $this->createSubscriber($connection)->beforeWrite($this->writeEvent($command));
    }

    public function testBeforeWriteIgnoresOtherEntities(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn('product');
        $command->expects($this->never())->method('getPayload');
        $command->expects($this->never())->method('addPayload');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $this->createSubscriber($connection)->beforeWrite($this->writeEvent($command));
    }

    public function testStripSecretRemovesTheSecretFromLoadedEntities(): void
    {
        $withSecret = new AdminAuthProviderEntity();
        $withSecret->setConfig(['clientId' => 'client-id', 'clientSecret' => 'encrypted-value']);

        $withoutSecret = new AdminAuthProviderEntity();
        $withoutSecret->setConfig(['clientId' => 'other-client']);

        $withoutConfig = new AdminAuthProviderEntity();

        $event = new EntityLoadedEvent(
            new AdminAuthProviderDefinition(),
            [$withSecret, $withoutSecret, $withoutConfig],
            Context::createDefaultContext()
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $this->createSubscriber($connection)->stripSecret($event);

        static::assertSame(['clientId' => 'client-id'], $withSecret->getConfig());
        static::assertSame(['clientId' => 'other-client'], $withoutSecret->getConfig());
        static::assertNull($withoutConfig->getConfig());
    }

    private function createSubscriber(Connection $connection): ProviderSecretSubscriber
    {
        return new ProviderSecretSubscriber($this->encryptor, $connection);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function command(array $payload, ?string $idBytes = null): WriteCommand&MockObject
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn(AdminAuthProviderDefinition::ENTITY_NAME);
        $command->method('getPayload')->willReturn($payload);
        $command->method('getPrimaryKey')->willReturn($idBytes === null ? [] : ['id' => $idBytes]);

        return $command;
    }

    private function writeEvent(WriteCommand $command): EntityWriteEvent
    {
        return EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command]
        );
    }
}
