<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Symfony\Component\Finder\Finder;

class ChangelogService
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @var ChangelogParser
     */
    private $changelogParser;

    public function __construct(ChangelogParser $changelogParser)
    {
        $this->changelogParser = $changelogParser;
    }

    public function getChangelogFiles(string $pluginPath): array
    {
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
        $fileName = basename($file, '.md');

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
        return $this->changelogParser->parseChangelog($file);
    }
}
