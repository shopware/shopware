<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogDefinition;
use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Changelog\ChangelogSection;
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
            return ['The given version is not released yet. Please specify another one.'];
        }

        $changelogFiles = $this->prepareChangelogFiles($version, $includeFeatureFlags);
        if (!$changelogFiles->count()) {
            return [
                $version ? 'There are no changelog files in this release version: ' . $version
                    : 'There are no unreleased changelog files at this moment',
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
        $getContentFnc = static fn (ChangelogDefinition $definition): ?string => null;
        $title = '';
        switch ($section) {
            case ChangelogSection::core->name:
                $title = ChangelogSection::core->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getCore();

                break;
            case ChangelogSection::api->name:
                $title = ChangelogSection::api->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getApi();

                break;
            case ChangelogSection::storefront->name:
                $title = ChangelogSection::storefront->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getStorefront();

                break;
            case ChangelogSection::administration->name:
                $title = ChangelogSection::administration->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getAdministration();

                break;
            case ChangelogSection::upgrade->name:
                $title = ChangelogSection::upgrade->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getUpgradeInformation();

                break;
            case ChangelogSection::major->name:
                $title = ChangelogSection::major->value;
                $getContentFnc = static fn (ChangelogDefinition $definition): ?string => $definition->getNextMajorVersionChanges();

                break;
        }
        $changes = [];
        foreach ($collection as $changelog) {
            $content = $getContentFnc($changelog->getDefinition());
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
