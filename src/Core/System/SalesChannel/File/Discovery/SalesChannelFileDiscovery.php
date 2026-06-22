<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Discovery;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplatePathIteratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileDiscovery
{
    public function __construct(
        private readonly TemplatePathIteratorInterface $templateIterator,
        private readonly TemplateFinder $templateFinder,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, SalesChannelFile>
     */
    public function discover(string $fileFamily = SalesChannelFile::DEFAULT_FILE_FAMILY): array
    {
        $files = [];

        foreach ($this->catalogueRegisteredFilesCached($fileFamily) as $fileName => $templatePath) {
            $file = $this->createFile($fileFamily, $fileName, $templatePath);

            if ($file !== null) {
                $files[$fileName] = $file;
            }
        }

        return $files;
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

        $templatePath = $this->catalogueRegisteredFilesCached($fileFamily)[$fileName] ?? null;
        if ($templatePath === null) {
            return null;
        }

        return $this->createFile($fileFamily, $fileName, $templatePath);
    }

    private function createFile(string $fileFamily, string $fileName, string $templatePath): ?SalesChannelFile
    {
        $templates = $this->resolveTemplateChainForFile($templatePath);

        if ($templates === []) {
            return null;
        }

        return new SalesChannelFile(
            $fileFamily,
            $fileName,
            $templatePath,
            $this->resolveContentType($fileName),
            $templatePath,
            $templates,
        );
    }

    /**
     * @return array<string, string>
     */
    private function catalogueRegisteredFilesCached(string $fileFamily): array
    {
        // Only the root file catalogue is cross-request stable. The resolved Twig chain
        // depends on the current namespace hierarchy, so it is rebuilt per call.
        return $this->cache->get(
            'sales-channel-file-discovery-' . Hasher::hash($fileFamily),
            function (ItemInterface $item) use ($fileFamily): array {
                $item->expiresAfter(null);

                return $this->catalogueRegisteredFiles($fileFamily);
            }
        );
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

    /**
     * @return array<string, string> Twig namespace mapped to resolved template name
     */
    private function resolveTemplateChainForFile(string $templatePath): array
    {
        $templates = [];
        $seen = [];
        $source = null;

        while (true) {
            try {
                $templateName = $this->templateFinder->find($templatePath, false, $source);
            } catch (LoaderError) {
                break;
            }

            if (isset($seen[$templateName])) {
                break;
            }

            $twigNamespace = $this->extractTwigNamespace($templateName);
            if ($twigNamespace === null) {
                break;
            }

            $templates[$twigNamespace] = $templateName;
            $seen[$templateName] = true;
            $source = $templateName;
        }

        return $templates;
    }

    private function extractTwigNamespace(string $templateName): ?string
    {
        if (!str_starts_with($templateName, '@')) {
            return null;
        }

        $position = mb_strpos($templateName, '/');
        if ($position === false) {
            return null;
        }

        return mb_substr($templateName, 1, $position - 1);
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
