<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Snippet;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\FilesystemAdministrationSnippets;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * Persists the administration snippets generated from legacy theme.json translations into the
 * private filesystem, where the administration picks them up as lowest-priority snippet layer.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeSnippetFileWriter
{
    public function __construct(
        private readonly ThemeConfigSnippetGenerator $generator,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function write(StorefrontPluginConfiguration $configuration): void
    {
        $technicalName = $configuration->getTechnicalName();
        $directory = FilesystemAdministrationSnippets::directoryFor($technicalName);

        $existed = $this->deleteDirectory($directory);
        $snippets = $this->generator->generate($configuration);

        if ($snippets === []) {
            if ($existed) {
                $this->invalidateCache();
            }

            return;
        }

        foreach ($snippets as $locale => $content) {
            $this->privateFilesystem->write(
                \sprintf('%s/%s', $directory, $this->generator->fileName($locale)),
                $this->generator->encode($content),
            );
        }

        $this->logger->warning(\sprintf(
            'Theme "%1$s" still defines "label" or "helpText" translations in its theme.json. They were converted into administration snippets; run "bin/console theme:migrate-translations %1$s" to move them into the theme.',
            $technicalName,
        ));

        $this->invalidateCache();
    }

    public function remove(string $technicalName): void
    {
        if ($this->deleteDirectory(FilesystemAdministrationSnippets::directoryFor($technicalName))) {
            $this->invalidateCache();
        }
    }

    /**
     * @return bool whether the directory existed before
     */
    private function deleteDirectory(string $directory): bool
    {
        if (!$this->privateFilesystem->directoryExists($directory)) {
            return false;
        }

        $this->privateFilesystem->deleteDirectory($directory);

        return true;
    }

    private function invalidateCache(): void
    {
        $this->cacheInvalidator->invalidate([FilesystemAdministrationSnippets::CACHE_TAG], true);
    }
}
