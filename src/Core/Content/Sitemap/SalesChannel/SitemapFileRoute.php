<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Extension\SitemapFileExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('services-settings')]
class SitemapFileRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $fileSystem,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    #[Route(path: '/store-api/sitemap/{filePath}', name: 'store-api.sitemap.file', requirements: ['filePath' => '.+\.xml\.gz'], methods: ['GET', 'POST'])]
    public function getSitemapFile(Request $request, SalesChannelContext $context, string $filePath): Response
    {
        return $this->extensions->publish(
            name: SitemapFileExtension::NAME,
            extension: new SitemapFileExtension($request, $context, $filePath),
            function: $this->_getSitemapFile(...)
        );
    }

    private function _getSitemapFile(Request $request, SalesChannelContext $context, string $filePath): Response
    {
        $filePath = 'sitemap/' . $filePath;

        if (!$this->isRequestedFileValid($context, $filePath)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $file = $this->fileSystem->readStream($filePath);

        if (!\is_resource($file)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $fileName = basename($filePath);

        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName,
                // only printable ascii
                preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $fileName) ?: ''
            ),
            'Content-Length' => $this->fileSystem->fileSize($filePath),
            'Content-Type' => 'application/octet-stream',
        ];

        return new StreamedResponse(function () use ($file): void {
            fpassthru($file);
        }, Response::HTTP_OK, $headers);
    }

    /**
     * Checks if the requested file is a valid sitemap file.
     */
    private function isRequestedFileValid(SalesChannelContext $salesChannelContext, string $filePath): bool
    {
        $files = $this->fileSystem->listContents('sitemap/salesChannel-' . $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getLanguageId());

        foreach ($files as $file) {
            if ($filePath === $file->path()) {
                return true;
            }
        }

        return false;
    }
}
