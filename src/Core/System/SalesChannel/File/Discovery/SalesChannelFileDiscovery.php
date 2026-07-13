<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Discovery;

use Shopware\Core\Framework\Adapter\Twig\TemplatePathIteratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileDiscovery
{
    public function __construct(
        private readonly TemplatePathIteratorInterface $templateIterator,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, SalesChannelFile>
     */
    public function discover(string $fileFamily = SalesChannelFile::DEFAULT_FILE_FAMILY): array
    {
        return $this->cache->get(
            'sales-channel-file-discovery-' . Hasher::hash($fileFamily),
            function (ItemInterface $item) use ($fileFamily): array {
                $item->expiresAfter(null);
                $item->tag(SalesChannelFileCacheInvalidator::buildDiscoveryCacheTag());

                return $this->discoverUncached($fileFamily);
            }
        );
    }

    public function get(string $templatePath): ?SalesChannelFile
    {
        $fileFamily = $this->extractFileFamily($templatePath);
        if ($fileFamily === null) {
            return null;
        }

        $fileName = $this->extractFileName($fileFamily, $templatePath);
        if ($fileName === null) {
            return null;
        }

        return $this->discover($fileFamily)[$fileName] ?? null;
    }

    /**
     * @return array<string, SalesChannelFile>
     */
    private function discoverUncached(string $fileFamily): array
    {
        $files = [];
        foreach ($this->catalogueRegisteredFiles($fileFamily) as $fileName => $templatePath) {
            $files[$fileName] = new SalesChannelFile(
                $fileFamily,
                $fileName,
                $templatePath,
                $this->resolveContentType($fileName),
                $templatePath,
                [],
            );
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function catalogueRegisteredFiles(string $fileFamily): array
    {
        $paths = [];
        $templatePathPrefix = SalesChannelFile::TEMPLATE_ROOT . '/' . $fileFamily . '/';

        // Template paths come from registered Twig templates; request path validation happens before loading public files.
        foreach ($this->templateIterator->getTemplatePathsForSubPath($templatePathPrefix, true) as $templatePath) {
            if (!str_ends_with($templatePath, SalesChannelFile::TEMPLATE_SUFFIX)) {
                continue;
            }

            $fileName = mb_substr($templatePath, mb_strlen($templatePathPrefix), -mb_strlen(SalesChannelFile::TEMPLATE_SUFFIX));
            $paths[$fileName] = $templatePath;
        }

        ksort($paths);

        return $paths;
    }

    private function extractFileFamily(string $templatePath): ?string
    {
        $prefix = SalesChannelFile::TEMPLATE_ROOT . '/';

        if (!str_starts_with($templatePath, $prefix)) {
            return null;
        }

        $fileFamily = mb_substr($templatePath, mb_strlen($prefix));
        $position = mb_strpos($fileFamily, '/');

        if ($position === false) {
            return null;
        }

        return mb_substr($fileFamily, 0, $position);
    }

    private function extractFileName(string $fileFamily, string $templatePath): ?string
    {
        $prefix = SalesChannelFile::TEMPLATE_ROOT . '/' . $fileFamily . '/';

        if (!str_starts_with($templatePath, $prefix) || !str_ends_with($templatePath, SalesChannelFile::TEMPLATE_SUFFIX)) {
            return null;
        }

        return mb_substr($templatePath, mb_strlen($prefix), -mb_strlen(SalesChannelFile::TEMPLATE_SUFFIX));
    }

    private function resolveContentType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $contentType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'text/plain';

        return $contentType . '; charset=utf-8';
    }
}
