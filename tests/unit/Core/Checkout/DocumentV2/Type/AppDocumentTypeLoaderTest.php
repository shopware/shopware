<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AppDocumentTypeLoader::class)]
class AppDocumentTypeLoaderTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoAppRegistersDocumentTypes(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame([], $loader->load());
    }

    public function testAdmitsAllCoreFormatsIncludingZugferd(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['html', 'pdf', 'zugferd_xml', 'zugferd_embedded_pdf'], \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame(
            ['swag_certificate' => ['html', 'pdf', 'zugferd_xml', 'zugferd_embedded_pdf']],
            $loader->load(),
        );
    }

    public function testDropsTypeWhenNoSupportedFormatRemains(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['bogus_format'], \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame([], $loader->load());
    }

    public function testMergesTypesFromMultipleActiveApps(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['html'], \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
            [
                'technical_name' => 'swag_warranty',
                'formats' => json_encode(['pdf'], \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame([
            'swag_certificate' => ['html'],
            'swag_warranty' => ['pdf'],
        ], $loader->load());
    }

    public function testMemoizesResultUntilReset(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([]);

        $loader = new AppDocumentTypeLoader($connection);

        $loader->load();
        $loader->load();

        $loader->reset();

        $loader->load();
    }

    public function testLoadConfigReturnsDecodedConfig(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['html', 'pdf'], \JSON_THROW_ON_ERROR),
                'config' => json_encode(['pageOrientation' => 'landscape', 'pageSize' => 'a4'], \JSON_THROW_ON_ERROR),
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame(
            ['pageOrientation' => 'landscape', 'pageSize' => 'a4'],
            $loader->loadConfig('swag_certificate'),
        );
    }

    public function testLoadConfigReturnsEmptyArrayForNullConfig(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['html'], \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame([], $loader->loadConfig('swag_certificate'));
    }

    public function testLoadConfigReturnsEmptyArrayForUnknownType(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame([], $loader->loadConfig('unknown_type'));
    }

    public function testLoadAndLoadConfigShareOneMemoizedQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'technical_name' => 'swag_certificate',
                'formats' => json_encode(['html'], \JSON_THROW_ON_ERROR),
                'config' => json_encode(['foo' => 'bar'], \JSON_THROW_ON_ERROR),
            ],
        ]);

        $loader = new AppDocumentTypeLoader($connection);

        static::assertSame(['swag_certificate' => ['html']], $loader->load());
        static::assertSame(['foo' => 'bar'], $loader->loadConfig('swag_certificate'));
    }
}
