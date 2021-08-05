<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogFileCollection;

class ChangelogReleaseCreator extends ChangelogProcessor
{
    /**
     * Start to release a given version
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

        $this->releaseChangelogFiles($output, $version, $changelogFiles, $dryRun);
        $this->releaseChangelogGlobal($output, $version, $changelogFiles, $dryRun);
        $this->releaseUpgradeInformation($output, $version, $changelogFiles, $dryRun);

        return $output;
    }

    /**
     * Collect all markdown files, which do not have a flag meta field, inside the `/changelog/_unreleased` directory
     * and move them to a new directory for the release in `/changelog/release-6-x-x-x`.
     */
    private function releaseChangelogFiles(array &$output, string $version, ChangelogFileCollection $collection, bool $dryRun = false): void
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
    }

    /**
     * Update the CHANGELOG.md global file
     */
    private function releaseChangelogGlobal(array &$output, string $version, ChangelogFileCollection $collection, bool $dryRun = false): void
    {
        $append = [];
        $append[] = sprintf('## %s', $version);

        $releaseDir = $this->getTargetReleaseDir($version, false);

        foreach ($collection as $changelog) {
            $log = sprintf(
                '*  [%s - %s](/changelog/%s)',
                $changelog->getDefinition()->getIssue(),
                $changelog->getDefinition()->getTitle(),
                $releaseDir . '/' . $changelog->getName()
            );

            $author = $changelog->getDefinition()->getAuthor() ?? '';
            $authorEmail = $changelog->getDefinition()->getAuthorEmail() ?? '';
            $github = $changelog->getDefinition()->getAuthorGitHub() ?? '';
            if (!empty($author) && !empty($github) && !empty($authorEmail) && strpos($authorEmail, '@shopware.com') === false) {
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
    }

    /**
     * Create / Update the Upgrade Information section, based on a given release version
     */
    private function releaseUpgradeInformation(array &$output, string $version, ChangelogFileCollection $collection, bool $dryRun = false): void
    {
        $append = [];
        foreach ($collection as $changelog) {
            if ($upgrade = $changelog->getDefinition()->getUpgradeInformation()) {
                $append[] = $upgrade;
            }
        }
        if (!\count($append)) {
            return;
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
    }
}
