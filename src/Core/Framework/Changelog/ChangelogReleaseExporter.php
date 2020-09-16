<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Symfony\Component\Filesystem\Filesystem;

class ChangelogReleaseExporter
{
    use ChangelogReleaseTrait;

    public function __construct(ChangelogParser $parser, Filesystem $filesystem, string $projectDir)
    {
        $this->parser = $parser;
        $this->filesystem = $filesystem;
        $this->initialize($projectDir);
    }

    /**
     * Export Changelog content by a given requested sections
     */
    public function export(array $requested, ?string $version = null, bool $includeFeatureFlags = false): array
    {
        $output = [];
        $changelogFiles = $this->prepareChangelogFiles($version, $includeFeatureFlags);
        if (!$changelogFiles->count()) {
            $output[] = $version ? 'There are not any changelog files in this release version: ' . $version
                : 'There are not any unreleased changelog files at this moment';

            return $output;
        }

        $output[] = $version ? 'All changes made in the version ' . $version : 'All unreleased changes made at this moment';
        $output[] = '===';
        foreach ($requested as $section => $enabled) {
            if ($enabled) {
                $this->exportByRequestedSection($output, $changelogFiles, $section);
            }
        }

        return $output;
    }

    private function exportByRequestedSection(array &$output, ChangelogFileCollection $collection, string $section): void
    {
        $getContentFnc = '';
        $title = '';
        switch ($section) {
            case 'core':
                $title = 'Core';
                $getContentFnc = 'getCore';

                break;
            case 'api':
                $title = 'API';
                $getContentFnc = 'getAdministration';

                break;
            case 'storefront':
                $title = 'Storefront';
                $getContentFnc = 'getStorefront';

                break;
            case 'admin':
                $title = 'Administration';
                $getContentFnc = 'getAdministration';

                break;
            case 'upgrade':
                $title = 'Upgrade Information';
                $getContentFnc = 'getUpgradeInformation';

                break;
        }
        $changes = [];
        foreach ($collection as $changelog) {
            $content = $changelog->getDefinition()->$getContentFnc();
            if (!empty($content)) {
                $changes[] = $content;
            }
        }

        if (count($changes)) {
            $output = array_merge($output, ['# ' . $title], $changes, ['---']);
        }
    }
}
