<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ChangelogReleaseExporter extends ChangelogProcessor
{
    /**
     * Export Changelog content by a given requested sections
     *
     * @param array<string, bool> $requested
     *
     * @return list<string>
     */
    public function export(array $requested, ?string $version = null, bool $includeFeatureFlags = false, bool $keysOnly = false): array
    {
        if ($version && !$this->existedRelease($version)) {
            return ['A given version did not released yet. Please specify another one.'];
        }

        $changelogFiles = $this->prepareChangelogFiles($version, $includeFeatureFlags);
        if (!$changelogFiles->count()) {
            return [
                $version ? 'There are not any changelog files in this release version: ' . $version
                    : 'There are not any unreleased changelog files at this moment',
            ];
        }

        $output = [];
        foreach ($requested as $section => $enabled) {
            if ($enabled) {
                if ($keysOnly) {
                    $output = $this->exportKeysByRequestedSection($output, $changelogFiles);
                } else {
                    $output = $this->exportByRequestedSection($output, $changelogFiles, $section);
                }
            }
        }

        if ($keysOnly) {
            $output = [implode(', ', $output)];
        }
        array_unshift($output, $version ? 'All changes made in the version ' . $version : 'All unreleased changes made at this moment', '===');

        return $output;
    }

    /**
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function exportKeysByRequestedSection(array $output, ChangelogFileCollection $collection): array
    {
        foreach ($collection as $changelog) {
            $content = $changelog->getDefinition()->getIssue();
            if (!isset($output[$content])) {
                $output[] = $content;
            }
        }

        return $output;
    }

    /**
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function exportByRequestedSection(array $output, ChangelogFileCollection $collection, string $section): array
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
            case 'major':
                $title = 'Next Major Version Change';
                $getContentFnc = 'getNextMajorVersionChanges';

                break;
        }
        $changes = [];
        foreach ($collection as $changelog) {
            $content = $changelog->getDefinition()->$getContentFnc();
            if (!empty($content)) {
                $changes[] = $content;
            }
        }

        if (\count($changes)) {
            $output = [...$output, ...['# ' . $title], ...$changes, ...['---']];
        }

        return $output;
    }
}
