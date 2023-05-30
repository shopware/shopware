<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ChangelogReleaseCreator extends ChangelogProcessor
{
    /**
     * Start to release a given version
     *
     * @return list<string>
     */
    public function release(string $version, bool $force = false, bool $dryRun = false): array
    {
        if (!$force && $this->existedRelease($version)) {
            throw new \InvalidArgumentException('A given version release existed already. Please specify another version or use "-f" to override the existing.');
        }

        $output = [];
        $changelogFiles = $this->prepareChangelogFiles();
        if (!$changelogFiles->count()) {
            $output[] = 'There are not any unreleased changelog files at this moment.';

            return $output;
        }

        $output = $this->releaseChangelogFiles($output, $version, $changelogFiles, $dryRun);
        $output = $this->releaseChangelogGlobal($output, $version, $changelogFiles, $dryRun);
        $output = $this->releaseUpgradeInformation($output, $version, $changelogFiles, $dryRun);
        $output = $this->releaseMajorUpgradeInformation($output, $version, $changelogFiles, $dryRun);

        return $output;
    }

    /**
     * Collect all markdown files, which do not have a flag meta field, inside the `/changelog/_unreleased` directory
     * and move them to a new directory for the release in `/changelog/release-6-x-x-x`.
     *
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function releaseChangelogFiles(array $output, string $version, ChangelogFileCollection $collection, bool $dryRun = false): array
    {
        $releaseDir = $this->getTargetReleaseDir($version);
        if (!$dryRun) {
            $this->filesystem->mkdir($releaseDir);
            $output[] = '* Move the unreleased changelog files to release folder: ' . $releaseDir;
        } else {
            $output[] = '---';
            $output[] = 'Move the unreleased changelog files to release folder: ' . $releaseDir;
            $output[] = '---';
        }

        foreach ($collection as $changelog) {
            if (!$dryRun) {
                $this->filesystem->rename($this->getUnreleasedDir() . '/' . $changelog->getName(), $releaseDir . '/' . $changelog->getName());
            } else {
                $output[] = '* ' . $changelog->getName();
            }
        }

        return $output;
    }

    /**
     * Update the CHANGELOG.md global file
     *
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function releaseChangelogGlobal(array $output, string $version, ChangelogFileCollection $collection, bool $dryRun = false): array
    {
        $append = [];
        $append[] = sprintf('## %s', $version);

        $releaseDir = $this->getTargetReleaseDir($version, false);

        foreach ($collection as $changelog) {
            $log = sprintf(
                '*  [%s - %s](./changelog/%s)',
                $changelog->getDefinition()->getIssue(),
                $changelog->getDefinition()->getTitle(),
                $releaseDir . '/' . $changelog->getName()
            );

            $author = $changelog->getDefinition()->getAuthor() ?? '';
            $authorEmail = $changelog->getDefinition()->getAuthorEmail() ?? '';
            $github = $changelog->getDefinition()->getAuthorGitHub() ?? '';
            if (!empty($author) && !empty($github) && !empty($authorEmail) && !str_contains($authorEmail, '@shopware.com')) {
                $log .= sprintf(' ([%s](https://github.com/%s))', $author, str_replace('@', '', $github));
            }

            $append[] = $log;
        }

        if (!$dryRun) {
            $content = file_get_contents($this->getChangelogGlobal()) ?: '';

            $posLatestRelease = strpos($content, '## ');
            $posLatestRelease = $posLatestRelease ?: 0;

            $content
                = substr($content, 0, $posLatestRelease)
                . implode("\n", $append) . "\n\n"
                . substr($content, $posLatestRelease);
            file_put_contents($this->getChangelogGlobal(), $content);

            $output[] = '* Update the CHANGELOG.md file';
        } else {
            $output[] = '---';
            $output[] = 'Update the CHANGELOG.md file';
            $output[] = '---';
            $output[] = implode("\n", $append);
        }

        return $output;
    }

    /**
     * Create / Update the Upgrade Information section, based on a given release version
     *
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function releaseUpgradeInformation(
        array $output,
        string $version,
        ChangelogFileCollection $collection,
        bool $dryRun = false
    ): array {
        $append = [];
        foreach ($collection as $changelog) {
            $upgrade = $changelog->getDefinition()->getUpgradeInformation();
            if ($upgrade) {
                $append[] = $upgrade;
            }
        }
        if (!\count($append)) {
            return $output;
        }

        array_unshift($append, sprintf('# %s', $version));

        $upgradeFile = $this->getTargetUpgradeFile($version);
        if (!$dryRun) {
            if (!$this->filesystem->exists($upgradeFile)) {
                $this->filesystem->touch($upgradeFile);
            }

            $content = file_get_contents($upgradeFile) ?: '';

            $posLatestRelease = strpos($content, '# ');
            $posLatestRelease = $posLatestRelease ?: 0;

            $content
                = substr($content, 0, $posLatestRelease)
                . implode("\n", $append) . "\n\n"
                . substr($content, $posLatestRelease);
            file_put_contents($upgradeFile, $content);

            $output[] = '* Update the Upgrade Information in: ' . $this->getTargetUpgradeFile($version, false);
        } else {
            $output[] = '---';
            $output[] = 'Update the Upgrade Information in: ' . $this->getTargetUpgradeFile($version, false);
            $output[] = '---';
            $output[] = implode("\n", $append);
        }

        return $output;
    }

    /**
     * Create / Update the Upgrade Information for the next major version, based on a given release version
     *
     * @param list<string> $output
     *
     * @return list<string>
     */
    private function releaseMajorUpgradeInformation(
        array $output,
        string $version,
        ChangelogFileCollection $collection,
        bool $dryRun = false
    ): array {
        $append = [];
        foreach ($collection as $changelog) {
            $upgrade = $changelog->getDefinition()->getNextMajorVersionChanges();
            if ($upgrade) {
                $append[] = $upgrade;
            }
        }
        if (!\count($append)) {
            return $output;
        }

        $nextMajorVersionHeadline = '# ' . $this->getNextMajorVersion($version) . '.0.0' . \PHP_EOL;

        array_unshift($append, sprintf('## Introduced in %s', $version));

        $upgradeFile = $this->getTargetNextMajorUpgradeFile($version);
        if (!$dryRun) {
            if (!$this->filesystem->exists($upgradeFile)) {
                $this->filesystem->touch($upgradeFile);
            }

            $content = file_get_contents($upgradeFile) ?: '';

            $posLatestRelease = strpos($content, '# ');
            $posLatestRelease = $posLatestRelease ?: 0;

            $content
                = substr($content, 0, $posLatestRelease)
                . $nextMajorVersionHeadline
                . implode("\n", $append) . "\n\n"
                . substr($content, $posLatestRelease + \strlen($nextMajorVersionHeadline));
            file_put_contents($upgradeFile, $content);

            $output[] = '* Update the Upgrade Information in: ' . $this->getTargetNextMajorUpgradeFile($version, false);
        } else {
            $output[] = '---';
            $output[] = 'Update the Upgrade Information in: ' . $this->getTargetNextMajorUpgradeFile($version, false);
            $output[] = '---';
            $output[] = implode("\n", $append);
        }

        return $output;
    }
}
