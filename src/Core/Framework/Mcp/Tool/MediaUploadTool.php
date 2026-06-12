<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-media-upload', title: 'Media Upload', description: 'Upload any image or file — including product cover images — to Shopware\'s media library from a URL. url is the only required parameter; productId, fileName, and mediaFolderId are all optional. Call this tool immediately with just the URL whenever the user asks to upload, import, or add an image. Returns the new mediaId.')]
#[McpToolRequires('media:create')]
#[McpToolRequires('product:update')]
#[Package('framework')]
class MediaUploadTool extends McpToolResponse
{
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

    private function assignToProduct(string $mediaId, string $productId, Context $context): void
    {
        $productMediaId = Uuid::randomHex();

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
