<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Symfony\Component\Finder\Finder;

class ChangelogService
{
    /**
     * @var ChangelogParserInterface
     */
    private $changelogParser;

    public function __construct(ChangelogParserInterface $changelogParser)
    {
        $this->changelogParser = $changelogParser;
    }

    public function getChangelogFiles(string $pluginPath): array
    {
        $finder = new Finder();

        $finder->files()->in($pluginPath)->name('CHANGELOG.md')->name('CHANGELOG-??_??.md');

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
            return Defaults::LOCALE_EN_GB_ISO;
        }

        return substr($fileName, strpos($fileName, '-') + 1, 5);
    }

    /**
     * @throws PluginChangelogInvalidException
     */
    public function parseChangelog(string $file): array
    {
        return $this->changelogParser->parseChangelog($file);
    }
}
