<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandler;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(MediaUrlPlaceholderHandler::class)]
class MediaUrlPlaceholderHandlerTest extends TestCase
{
    private MockObject&Connection $connection;

    private MediaUrlPlaceholderHandlerInterface $mediaUrlPlaceholderHandler;

    private const MEDIA1_ID = 'ade8de5aba434c6c8b871e7785d57596';
    private const MEDIA2_ID = 'f120665e491849d38f1a94e912fbc7e3';
    private const MEDIA3_ID = 'b897b4b7c8394387ac88341951816613';
    private const PRODUCT_ID = 'ad518375caa8445caad158291c0c5234';
    private const DATETIME = '2024-05-14 13:37:00';

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $fileSystemOperator = $this->createMock(Filesystem::class);
        $fileSystemOperator->expects(static::any())->method('publicUrl')->willReturnCallback(function ($path) {
            return 'http://foo.text:8000/' . $path;
        });

        $this->mediaUrlPlaceholderHandler = new MediaUrlPlaceholderHandler(
            $this->connection,
            new MediaUrlGenerator($fileSystemOperator)
        );
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function replaceDataProvider(): iterable
    {
        yield 'one url' => [
            'content' => 'Test content with url ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA1_ID . '#.',
            'expected' => 'Test content with url http://foo.text:8000/media/12/34/cat.pdf?ts=' . strtotime(self::DATETIME) . '.',
        ];

        yield 'two urls' => [
            'content' => 'Test URL 1: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA1_ID . '# and URL 2: '
                . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA2_ID . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/media/12/34/cat.pdf?ts=' . strtotime(self::DATETIME) . ' and URL 2: http://foo.text:8000/media/56/78/dog.pdf?ts=' . strtotime(self::DATETIME),
        ];

        yield 'two equal urls' => [
            'content' => 'Test URL 1: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA1_ID . '# and URL 2: '
                . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA1_ID . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/media/12/34/cat.pdf?ts=' . strtotime(self::DATETIME) . ' and URL 2: http://foo.text:8000/media/12/34/cat.pdf?ts=' . strtotime(self::DATETIME),
        ];

        yield 'product urls left untouched' => [
            'content' => 'Test URL 1: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA1_ID . '# and URL 2: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . self::PRODUCT_ID . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/media/12/34/cat.pdf?ts=' . strtotime(self::DATETIME) . ' and URL 2: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . self::PRODUCT_ID . '#',
        ];

        yield 'handle not found' => [
            'content' => 'Test URL 1: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA3_ID,
            'expected' => 'Test URL 1: ' . MediaUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/mediaId/' . self::MEDIA3_ID,
        ];
    }

    #[DataProvider('replaceDataProvider')]
    public function testReplace(string $content, string $expected): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(static::any())->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::fromHexToBytes(self::MEDIA1_ID),
                'path' => 'media/12/34/cat.pdf',
                'created_at' => self::DATETIME,
                'updated_at' => self::DATETIME,
            ],
            [
                'id' => Uuid::fromHexToBytes(self::MEDIA2_ID),
                'path' => 'media/56/78/dog.pdf',
                'created_at' => self::DATETIME,
                'updated_at' => self::DATETIME,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);
        static::assertSame($expected, $this->mediaUrlPlaceholderHandler->replace($content));
    }
}
