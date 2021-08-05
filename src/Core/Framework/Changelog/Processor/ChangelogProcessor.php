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
    protected Filesystem $filesystem;

    protected ChangelogParser $parser;

    protected ValidatorInterface $validator;

    private string $projectDir;

    public function __construct(ChangelogParser $parser, ValidatorInterface $validator, Filesystem $filesystem, string $projectDir)
    {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->filesystem = $filesystem;
        $this->projectDir = $projectDir;
    }

    protected function getUnreleasedDir(): string
    {
        return $this->getChangelogDir() . '/_unreleased';
    }

    protected function getChangelogDir(): string
    {
        return $this->getPlatformRoot() . '/changelog';
    }

    protected function getChangelogGlobal(): string
    {
        return $this->getPlatformRoot() . '/CHANGELOG.md';
    }

    protected function getUpgradeDir(): string
    {
        return $this->getPlatformRoot();
    }

    protected function existedRelease(string $version): bool
    {
        return $this->filesystem->exists($this->getTargetReleaseDir($version));
    }

    protected function getTargetReleaseDir(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getChangelogDir() . '/' : '') . 'release-' . str_replace('.', '-', $version);
    }

    protected function getMajorVersion(string $version): string
    {
        return substr($version, 0, (int) strpos($version, '.', strpos($version, '.') + \strlen('.')));
    }

    protected function getTargetUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getUpgradeDir() . '/' : '') . sprintf('UPGRADE-%s.md', $this->getMajorVersion($version));
    }

    /**
     * Prepare the list of changelog files which need to process
     */
    protected function prepareChangelogFiles(?string $version = null, bool $includeFeatureFlags = false): ChangelogFileCollection
    {
        $entries = new ChangelogFileCollection();

        $finder = new Finder();
        $finder->in($version ? $this->getTargetReleaseDir($version) : $this->getUnreleasedDir())->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $definition = $this->parser->parse($file->getContents());
                if (\count($this->validator->validate($definition))) {
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

    private function getPlatformRoot(): string
    {
        $platformRoot = $this->projectDir;
        $composerJson = json_decode((string) file_get_contents($this->projectDir . '/composer.json'), true);

        if ($composerJson === null || $composerJson['name'] !== 'shopware/platform') {
            $platformRoot .= '/platform';
        }

        return $platformRoot;
    }
}
