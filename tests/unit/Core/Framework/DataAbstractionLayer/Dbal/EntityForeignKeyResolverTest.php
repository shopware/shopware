<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityForeignKeyResolver::class)]
class EntityForeignKeyResolverTest extends TestCase
{
    private Connection&MockObject $connection;

    private EntityForeignKeyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);

        $this->resolver = new EntityForeignKeyResolver(
            $this->connection,
            $this->createMock(EntityDefinitionQueryHelper::class)
        );
    }

    public function testMetaFieldsEnrichedInRestrictions(): void
    {
        $mediaId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $downloadId = Uuid::randomHex();

        $emptyResult = $this->createMock(Result::class);

        $downloadResult = $this->createMock(Result::class);
        $downloadResult->method('fetchAllAssociative')->willReturn([
            [
                'id' => $downloadId,
                '_id' => Uuid::fromHexToBytes($mediaId),
                '_fileName' => 'shopware-logo',
                '_fileExtension' => 'png',
            ],
        ]);

        $cmsPageResult = $this->createMock(Result::class);
        $cmsPageResult->method('fetchAllAssociative')->willReturn([
            [
                'id' => $cmsPageId,
                '_id' => Uuid::fromHexToBytes($mediaId),
                '_fileName' => 'shopware-logo',
                '_fileExtension' => 'png',
            ],
        ]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $downloadResult,
            $emptyResult,
            $emptyResult,
            $emptyResult,
            $cmsPageResult,
            $emptyResult,
            $emptyResult,
            $emptyResult,
        );

        $rootDef = $this->buildRegistry();

        $result = $this->resolver->getAffectedDeleteRestrictions(
            $rootDef,
            [['id' => $mediaId]],
            Context::createDefaultContext(),
            true
        );

        static::assertSame([
            'product_download' => [
                [
                    'id' => $downloadId,
                    'media' => [
                        'id' => $mediaId,
                        'fileExtension' => 'png',
                        'fileName' => 'shopware-logo',
                    ],
                ],
            ],
            'cms_page' => [
                [
                    'id' => $cmsPageId,
                    'media' => [
                        'id' => $mediaId,
                        'fileExtension' => 'png',
                        'fileName' => 'shopware-logo',
                    ],
                ],
            ],
        ], $result);
    }

    private function buildRegistry(): MediaDefinition
    {
        $rootDef = new MediaDefinition();

        new StaticDefinitionInstanceRegistry(
            [
                $rootDef,
                new CmsPageDefinition(),
                new CmsBlockDefinition(),
                new CmsSectionDefinition(),
                new ProductDownloadDefinition(),
                new OrderLineItemDownloadDefinition(),
                new DocumentDefinition(),
                new DocumentFileDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $rootDef;
    }
}
