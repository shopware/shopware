<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

trait ChangelogReleaseTrait
{
    /** @var ChangelogParser */
    private $parser;

    /** @var Filesystem */
    private $filesystem;

    /** @var string */
    private $changelogDir;

    /** @var string */
    private $unreleasedDir;

    /** @var string */
    private $changelogGlobal;

    /** @var string */
    private $upgradeDir;

    private function initialize(string $projectDir): void
    {
        $this->changelogDir = $projectDir . '/platform/changelog';
        $this->unreleasedDir = $this->changelogDir . '/_unreleased';
        $this->changelogGlobal = $projectDir . '/platform/CHANGELOG.md';
        $this->upgradeDir = $projectDir . '/platform';
    }

    private function existedRelease(string $version): bool
    {
        return $this->filesystem->exists($this->getTargetReleaseDir($version));
    }

    private function getTargetReleaseDir(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->changelogDir . '/' : '') . 'release-' . str_replace('.', '-', $version);
    }

    private function getMajorVersion(string $version): string
    {
        return substr($version, 0, (int) strpos($version, '.', strpos($version, '.') + strlen('.')));
    }

    private function getTargetUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->upgradeDir . '/' : '') . sprintf('UPGRADE-%s.md', $this->getMajorVersion($version));
    }

    /**
     * Prepare the list of changelog files which need to process
     */
    private function prepareChangelogFiles(?string $version = null, bool $includeFeatureFlags = false): ChangelogFileCollection
    {
        $entries = new ChangelogFileCollection();

        $finder = new Finder();
        $finder->in($version ? $this->getTargetReleaseDir($version) : $this->unreleasedDir)->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $definition = $this->parser->parse($file->getContents());
                if (!$definition->isValid()) {
                    throw new \InvalidArgumentException('Bad syntax FOUND in ' . $file->getRealPath());
                }
                if (!$includeFeatureFlags && $definition->getFlag()) {
                    continue;
                }
                $entries->add(
                    (new ChangelogFile())
                        ->setName($file->getFilename())
                        ->setPath((string) $file->getRealPath())
                        ->setDefinition($definition)
                );
            }
        }

        return $entries;
    }
}
