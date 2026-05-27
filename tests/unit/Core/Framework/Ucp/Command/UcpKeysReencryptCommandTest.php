<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Ucp\Command\UcpKeysReencryptCommand;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSigningKeyCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Security\PrivateKeyEncryptor;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UcpKeysReencryptCommand::class)]
class UcpKeysReencryptCommandTest extends TestCase
{
    public function testRequiresOldSecret(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('any')));
        $exit = $tester->execute(['--new-secret' => 'new']);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('--old-secret is required', $tester->getDisplay());
    }

    public function testIdenticalSecretsReturnsEarly(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('any')));
        $exit = $tester->execute(['--old-secret' => 'same', '--new-secret' => 'same']);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('Old and new secret are identical', $tester->getDisplay());
    }

    public function testReencryptsAllRowsAndPersists(): void
    {
        $oldSecret = 'old-secret-must-be-long-enough';
        $newSecret = 'new-secret-must-be-long-enough';

        $kid1 = 'ucp_2026_aaaa';
        $kid2 = 'ucp_2026_bbbb';
        $plaintext1 = "-----BEGIN PRIVATE KEY-----\nKEY1\n-----END PRIVATE KEY-----\n";
        $plaintext2 = "-----BEGIN PRIVATE KEY-----\nKEY2\n-----END PRIVATE KEY-----\n";

        $oldEncryptor = new PrivateKeyEncryptor($oldSecret);
        $key1 = $this->makeKey($kid1, $oldEncryptor->encrypt($plaintext1, $kid1));
        $key2 = $this->makeKey($kid2, $oldEncryptor->encrypt($plaintext2, $kid2));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->willReturn(
            $this->makeSearchResult([$key1, $key2])
        );

        $captured = null;
        $writtenEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $repository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $writes, Context $ctx) use (&$captured, $writtenEvent) {
                $captured = $writes;

                return $writtenEvent;
            });

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('ignored')));
        $exit = $tester->execute(['--old-secret' => $oldSecret, '--new-secret' => $newSecret]);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertIsArray($captured);
        static::assertCount(2, $captured);

        $newEncryptor = new PrivateKeyEncryptor($newSecret);
        static::assertSame($plaintext1, $newEncryptor->decrypt($captured[0]['privateKeyPemEncrypted'], $kid1));
        static::assertSame($plaintext2, $newEncryptor->decrypt($captured[1]['privateKeyPemEncrypted'], $kid2));
        static::assertStringContainsString('Re-encrypted 2 UCP signing key(s)', $tester->getDisplay());
    }

    public function testDryRunDoesNotPersist(): void
    {
        $oldSecret = 'old-secret-must-be-long-enough';
        $newSecret = 'new-secret-must-be-long-enough';
        $kid = 'ucp_2026_dryrun';
        $key = $this->makeKey($kid, (new PrivateKeyEncryptor($oldSecret))->encrypt('payload', $kid));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$key]));
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('ignored')));
        $exit = $tester->execute([
            '--old-secret' => $oldSecret,
            '--new-secret' => $newSecret,
            '--dry-run' => true,
        ]);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('[dry-run]', $tester->getDisplay());
    }

    public function testWrongOldSecretAbortsBeforeWriting(): void
    {
        $kid = 'ucp_2026_kid';
        $key = $this->makeKey($kid, (new PrivateKeyEncryptor('correct-secret'))->encrypt('payload', $kid));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$key]));
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('ignored')));
        $exit = $tester->execute([
            '--old-secret' => 'wrong-secret',
            '--new-secret' => 'new-secret',
        ]);

        static::assertSame(Command::FAILURE, $exit);
        static::assertStringContainsString('Re-encryption failed', $tester->getDisplay());
        static::assertStringContainsString($kid, $tester->getDisplay());
    }

    public function testEmptyResultIsReportedAsNoop(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([]));
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new UcpKeysReencryptCommand($repository, new PrivateKeyEncryptor('ignored')));
        $exit = $tester->execute([
            '--old-secret' => 'old',
            '--new-secret' => 'new',
            '--sales-channel' => Uuid::randomHex(),
        ]);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('No UCP signing keys found', $tester->getDisplay());
    }

    private function makeKey(string $kid, string $encryptedBlob): UcpSigningKeyEntity
    {
        $key = new UcpSigningKeyEntity();
        $key->setId(Uuid::randomHex());
        $key->setSalesChannelId('0190f7bfb9c97e8c8d28b6e5d6a45f00');
        $key->setKid($kid);
        $key->setAlgorithm(UcpSigningKeyEntity::ALGORITHM_ES256);
        $key->setPublicJwk(['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid]);
        $key->setPrivateKeyPemEncrypted($encryptedBlob);
        $key->setStatus(UcpSigningKeyEntity::STATUS_ACTIVE);

        return $key;
    }

    /**
     * @param list<UcpSigningKeyEntity> $entities
     *
     * @return EntitySearchResult<UcpSigningKeyCollection>
     */
    private function makeSearchResult(array $entities): EntitySearchResult
    {
        $collection = new UcpSigningKeyCollection($entities);

        return new EntitySearchResult(
            UcpSigningKeyEntity::class,
            \count($entities),
            $collection,
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
