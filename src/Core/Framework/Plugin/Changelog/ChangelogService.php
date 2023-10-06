<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated tag:v6.6.0 - will be removed without a replacement
 */
#[Package('core')]
class ChangelogService
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @internal
     */
    public function __construct(private readonly ChangelogParser $changelogParser)
    {
    }

    public function getChangelogFiles(string $pluginPath): array
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        $finder = new Finder();

        $finder->files()->in($pluginPath)->name('CHANGELOG.md')->name('CHANGELOG_??-??.md')->depth(0);

        $files = [];

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    public function getLocaleFromChangelogFile($file): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        $fileName = basename((string) $file, '.md');

        if ($fileName === 'CHANGELOG') {
            return self::FALLBACK_LOCALE;
        }

        return mb_substr($fileName, mb_strpos($fileName, '_') + 1, 5);
    }

    /**
     * @throws PluginChangelogInvalidException
     */
    public function parseChangelog(string $file): array
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        return $this->changelogParser->parseChangelog($file);
    }
}
