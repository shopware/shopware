<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\MediaUploadTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MediaUploadTool::class)]
class MediaUploadToolTest extends TestCase
{
    public function testUploadFromUrlReturnsMediaId(): void
    {
        $mediaId = 'generated-media-id';

        $uploadService = $this->createMock(MediaUploadService::class);
        $uploadService->expects($this->once())
            ->method('uploadFromURL')
            ->willReturn($mediaId);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create']));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame($mediaId, $data['data']['mediaId']);
    }

    public function testUploadWithProductAssignment(): void
    {
        $mediaId = 'generated-media-id';
        $productId = 'product-id';

        $uploadService = $this->createMock(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willReturn($mediaId);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create', 'product:update']));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('upsert');

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('product')->willReturn($repository);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg', productId: $productId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame($mediaId, $data['data']['mediaId']);
        static::assertSame($productId, $data['data']['productId']);
        static::assertTrue($data['data']['assignedAsCover']);
    }

    public function testUploadExceptionReturnsError(): void
    {
        $uploadService = static::createStub(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willThrowException(new \RuntimeException('Download failed'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create']));

        $tool = new MediaUploadTool($uploadService, $contextProvider, static::createStub(DefinitionInstanceRegistry::class));
        $output = $tool('https://example.com/broken.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Upload failed', $data['error']);
        static::assertStringContainsString('Download failed', $data['error']);
    }

    public function testProductAssignmentExceptionReturnsError(): void
    {
        $mediaId = 'generated-media-id';

        $uploadService = static::createStub(MediaUploadService::class);
        $uploadService->method('uploadFromURL')->willReturn($mediaId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['media:create', 'product:update']));

        $repository = static::createStub(EntityRepository::class);
        $repository->method('upsert')->willThrowException(new \RuntimeException('Product not found'));

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg', productId: 'bad-product');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('product assignment failed', $data['error']);
        static::assertStringContainsString($mediaId, $data['error']);
    }

    public function testMissingAclReturnsError(): void
    {
        $uploadService = $this->createMock(MediaUploadService::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext([]));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $tool = new MediaUploadTool($uploadService, $contextProvider, $registry);
        $output = $tool('https://example.com/image.jpg');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
    }

    /**
     * @param list<string> $privileges
     */
    private function createAdminContext(array $privileges): Context
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        return new Context($source);
    }
}
