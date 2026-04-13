<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlPersister::class)]
class SeoUrlPersisterTest extends TestCase
{
    private Connection&MockObject $connection;

    private SeoUrlPersister $seoUrlPersister;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->seoUrlPersister = new SeoUrlPersister(
            $this->connection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    public function testUpdateSeoUrlsWithNewSeoPaths(): void
    {
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

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connection->expects($this->never())
            ->method('fetchOne')
            ->willReturn([]);

        $this->connection->expects($this->never())
            ->method('executeStatement');

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $this->seoUrlPersister->updateSeoUrls(
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

        $this->connection->expects($this->once())
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

        $this->connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls($id1, $id2);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id IN (:ids)',
                ['ids' => $expectedIds],
                ['ids' => ArrayParameterType::BINARY]
            );

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }
}
