<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Api\ProductActionController;
use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Tests\Unit\Common\Stubs\IniMock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductActionController::class)]
class ProductActionControllerTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public static function setUpBeforeClass(): void
    {
        // register before the first ini_get(), or the ini mocks of later tests stop working
        IniMock::register(MemorySizeCalculator::class);
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    protected function tearDown(): void
    {
        IniMock::withIniMock([]);
    }

    public static function documentUploadLimitProvider(): iterable
    {
        yield 'uploads through PHP are lowered by upload_max_filesize' => [false, 512];
        yield 'presigned uploads go past PHP' => [true, 1024];
    }

    #[DataProvider('documentUploadLimitProvider')]
    public function testDocumentUploadSettingsGiveTheUploadLimit(bool $presignedUploadAvailable, int $expected): void
    {
        IniMock::withIniMock(['upload_max_filesize' => '512', 'post_max_size' => '2048']);
        $presignedMediaUploadService = static::createStub(PresignedMediaUploadService::class);
        $presignedMediaUploadService->method('isAvailable')->willReturn($presignedUploadAvailable);

        $settings = $this->getDocumentUploadSettings($presignedMediaUploadService);

        static::assertSame($expected, $settings['maxFileSize']);
    }

    public function testDocumentUploadSettingsDispatchTheFileTypesEventOnlyOnce(): void
    {
        $dispatchCount = 0;
        $this->eventDispatcher->addListener(ProductDocumentAllowedExtensionsEvent::class, static function (ProductDocumentAllowedExtensionsEvent $event) use (&$dispatchCount): void {
            ++$dispatchCount;
            $event->setAllowedExtensions(['pdf']);
        });

        $settings = $this->getDocumentUploadSettings();

        static::assertSame(1, $dispatchCount);
        static::assertSame(['pdf'], $settings['allowedExtensions']);
        static::assertSame(['pdf'], array_keys($settings['allowedMimeTypesByExtension']));
    }

    public function testDocumentUploadSettingsReturnNumericFileTypesAsStrings(): void
    {
        $this->eventDispatcher->addListener(ProductDocumentAllowedExtensionsEvent::class, static function (ProductDocumentAllowedExtensionsEvent $event): void {
            $event->setAllowedExtensions(['pdf', '264']);
        });

        $settings = $this->getDocumentUploadSettings();

        static::assertSame(['pdf', '264'], $settings['allowedExtensions']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getDocumentUploadSettings(?PresignedMediaUploadService $presignedMediaUploadService = null): array
    {
        $controller = new ProductActionController(
            static::createStub(VariantCombinationLoader::class),
            new ProductTypeRegistry([]),
            new ProductDocumentConfig($this->eventDispatcher, ['pdf', 'docx'], 1024, ['pdf', 'docx', '264']),
            $presignedMediaUploadService,
        );

        $settings = json_decode((string) $controller->getDocumentUploadSettings(Context::createDefaultContext())->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($settings);

        return $settings;
    }
}
