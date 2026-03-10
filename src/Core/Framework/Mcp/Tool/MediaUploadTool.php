<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-media-upload', description: 'Upload a media file from a public URL. Optionally assign it as the cover image of a product. Returns the created media ID. When productId is provided, the media is linked as product media and set as the cover image.')]
#[Package('framework')]
class MediaUploadTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly McpContextProvider $contextProvider,
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(
        string $url,
        string $fileName = '',
        string $mediaFolderId = '',
        string $productId = '',
    ): string {
        $context = $this->contextProvider->getContext();

        $requiredPrivileges = ['media:create'];
        if ($productId !== '') {
            $requiredPrivileges[] = 'product:update';
        }

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
            return $error;
        }

        $params = new MediaUploadParameters(
            mediaFolderId: $mediaFolderId !== '' ? $mediaFolderId : null,
            fileName: $fileName !== '' ? $fileName : null,
        );

        try {
            $mediaId = $this->mediaUploadService->uploadFromURL($url, $context, $params);
        } catch (\Throwable $e) {
            return $this->error('Upload failed: ' . $e->getMessage());
        }

        $result = ['mediaId' => $mediaId];

        if ($productId !== '') {
            try {
                $this->assignToProduct($mediaId, $productId, $context);
                $result['productId'] = $productId;
                $result['assignedAsCover'] = true;
            } catch (\Throwable $e) {
                return $this->error('Media uploaded (ID: ' . $mediaId . ') but product assignment failed: ' . $e->getMessage());
            }
        }

        return $this->success($result);
    }

    private function assignToProduct(string $mediaId, string $productId, \Shopware\Core\Framework\Context $context): void
    {
        $productMediaId = \Shopware\Core\Framework\Uuid\Uuid::randomHex();

        $this->registry->getRepository('product')->upsert([
            [
                'id' => $productId,
                'media' => [
                    [
                        'id' => $productMediaId,
                        'mediaId' => $mediaId,
                    ],
                ],
                'coverId' => $productMediaId,
            ],
        ], $context);
    }
}
