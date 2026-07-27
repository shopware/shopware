<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlPersister::class)]
class SeoUrlPersisterTest extends TestCase
{
    private SeoUrlPersister $seoUrlPersister;

    protected function setUp(): void
    {
        $this->seoUrlPersister = $this->createSeoUrlPersister(static::createStub(Connection::class));
    }

    public function testUpdateSeoUrlsWithNewSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path2',
                'seoPathInfo' => 'path2',
            ],
        ];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('fetchOne')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('executeStatement');

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    /**
     * Regression for shopware/shopware#4413: when a SEO URL is write-protected (is_modified=1),
     * the legacy guard in skipUpdate() should still protect it against automatic template
     * regeneration (default overwrite=false).
     */
    public function testSkipUpdateProtectsWriteProtectedSeoUrlByDefault(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'manual-path',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'template-path',
            'isModified' => false,
        ];

        static::assertTrue($this->invokeSkipUpdate($existing, $payload, false));
    }

    /**
     * Regression for shopware/shopware#4413: when the admin/API explicitly requests an overwrite,
     * the skipUpdate() guard must no longer protect write-protected SEO URLs from being
     * replaced with the template-generated path.
     */
    public function testSkipUpdateAllowsResetWithOverwrite(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'manual-path',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'template-path',
            'isModified' => false,
        ];

        static::assertFalse($this->invokeSkipUpdate($existing, $payload, true));
    }

    /**
     * Regression for shopware/shopware#4413: the admin must be able to persist an edit that
     * replaces the write-protected path with a new manual path.
     */
    public function testSkipUpdateAllowsManualEditWithOverwrite(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'manual-path',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'manual-path-edited',
            'isModified' => true,
        ];

        static::assertFalse($this->invokeSkipUpdate($existing, $payload, true));
    }

    /**
     * Regression for shopware/shopware#4413: even with overwrite=true the path-equality guard
     * must still short-circuit, so re-saving the exact same SEO URL does not create a duplicate.
     */
    public function testSkipUpdateStillSkipsIdenticalPayloadWithOverwrite(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'manual-path',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'manual-path',
            'isModified' => true,
        ];

        static::assertTrue($this->invokeSkipUpdate($existing, $payload, true));
    }

    /**
     * Regression for shopware/shopware#4413 (same-path reset): when a write-protected URL
     * already equals the template output, an explicit overwrite that only flips isModified
     * to false must NOT be skipped, so the write-protection flag is actually dropped.
     */
    public function testSkipUpdateClearsProtectionOnIdenticalPathWithOverwrite(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'red-shoe',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'red-shoe',
            'isModified' => false,
        ];

        static::assertFalse($this->invokeSkipUpdate($existing, $payload, true));
    }

    /**
     * Without overwrite the same-path automatic regeneration must keep skipping, regardless of
     * the requested isModified state, so it never creates duplicate rows.
     */
    public function testSkipUpdateKeepsSkippingIdenticalPathWithoutOverwrite(): void
    {
        $existing = [
            'id' => 'id-1',
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'isModified' => true,
            'seoPathInfo' => 'red-shoe',
        ];
        $payload = [
            'foreignKey' => 'fk-1',
            'salesChannelId' => 'sc-1',
            'seoPathInfo' => 'red-shoe',
            'isModified' => false,
        ];

        static::assertTrue($this->invokeSkipUpdate($existing, $payload, false));
    }

    public function testForceUpdateSeoUrlsPersistsNewSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
        ];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('fetchOne');

        $connection->expects($this->never())
            ->method('executeStatement');

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->forceUpdateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    public function testUpdateSeoUrlsWithInuseSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path2',
                'seoPathInfo' => 'path2',
            ],
        ];

        $id1 = Uuid::randomBytes();
        $id2 = Uuid::randomBytes();
        $expectedIds = [$id1, $id2];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'id1',
                    'languageId' => Uuid::randomHex(),
                    'salesChannelId' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'routeName' => 'test-route',
                ],
                [
                    'id' => 'id2',
                    'languageId' => Uuid::randomHex(),
                    'salesChannelId' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'routeName' => 'test-route',
                ],
            ]);

        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls($id1, $id2);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id IN (:ids)',
                ['ids' => $expectedIds],
                ['ids' => ArrayParameterType::BINARY]
            );

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    /**
     * @param array{isModified: bool, seoPathInfo: string, salesChannelId: string} $existing
     * @param array{isModified?: bool, seoPathInfo: string, salesChannelId: string} $seoUrl
     */
    private function invokeSkipUpdate(array $existing, array $seoUrl, bool $overwrite): bool
    {
        $reflection = new \ReflectionMethod(SeoUrlPersister::class, 'skipUpdate');
        $reflection->setAccessible(true);

        return (bool) $reflection->invoke($this->seoUrlPersister, $existing, $seoUrl, $overwrite);
    }

    private function createSeoUrlPersister(Connection $connection): SeoUrlPersister
    {
        return new SeoUrlPersister(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new NativeClock()
        );
    }
}
