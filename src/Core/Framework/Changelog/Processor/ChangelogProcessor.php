<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogFile;
use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Changelog\ChangelogParser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChangelogProcessor
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $changelogDir;

    /**
     * @var string
     */
    protected $unreleasedDir;

    /**
     * @var string
     */
    protected $changelogGlobal;

    /**
     * @var string
     */
    protected $upgradeDir;

    /**
     * @var ChangelogParser
     */
    protected $parser;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(ChangelogParser $parser, ValidatorInterface $validator, Filesystem $filesystem, string $projectDir)
    {
        $this->changelogDir = $projectDir . '/platform/changelog';
        $this->unreleasedDir = $this->changelogDir . '/_unreleased';
        $this->changelogGlobal = $projectDir . '/platform/CHANGELOG.md';
        $this->upgradeDir = $projectDir . '/platform';
        $this->parser = $parser;
        $this->validator = $validator;
        $this->filesystem = $filesystem;
    }

    protected function existedRelease(string $version): bool
    {
        return $this->filesystem->exists($this->getTargetReleaseDir($version));
    }

    protected function getTargetReleaseDir(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->changelogDir . '/' : '') . 'release-' . str_replace('.', '-', $version);
    }

    protected function getMajorVersion(string $version): string
    {
        return substr($version, 0, (int) strpos($version, '.', strpos($version, '.') + strlen('.')));
    }

    protected function getTargetUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->upgradeDir . '/' : '') . sprintf('UPGRADE-%s.md', $this->getMajorVersion($version));
    }

    /**
     * Prepare the list of changelog files which need to process
     */
    protected function prepareChangelogFiles(?string $version = null, bool $includeFeatureFlags = false): ChangelogFileCollection
    {
        $entries = new ChangelogFileCollection();

        $finder = new Finder();
        $finder->in($version ? $this->getTargetReleaseDir($version) : $this->unreleasedDir)->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $definition = $this->parser->parse($file->getContents());
                if (count($this->validator->validate($definition))) {
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
