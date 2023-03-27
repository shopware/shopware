<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogFile;
use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Changelog\ChangelogParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function array_map;
use function implode;
use function iterator_to_array;
use function sprintf;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('core')]
class ChangelogProcessor
{
    private ?string $platformRoot = null;

    /**
     * @param array<string, FeatureFlagConfig> $featureFlags
     */
    public function __construct(
        protected ChangelogParser $parser,
        protected ValidatorInterface $validator,
        protected Filesystem $filesystem,
        private readonly string $projectDir,
        protected array $featureFlags
    ) {
    }

    /**
     * @internal
     */
    public function setPlatformRoot(string $platformRoot): void
    {
        $this->platformRoot = $platformRoot;
    }

    /**
     * @internal
     *
     * @param array<string, FeatureFlagConfig> $flags
     */
    public function setActiveFlags(array $flags): void
    {
        $this->featureFlags = $flags;
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

    /**
     * @internal
     */
    protected function getNextMajorVersion(string $version): string
    {
        [$superVersion, $majorVersion] = explode('.', $version);

        if (!is_numeric($superVersion) || !is_numeric($majorVersion)) {
            throw new \InvalidArgumentException('Unable to generate next version number, supplied version seems invalid (' . $version . ')');
        }

        $superVersion = (int) $superVersion;
        $majorVersion = (int) $majorVersion;

        return $superVersion . '.' . ($majorVersion + 1);
    }

    protected function getTargetUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getUpgradeDir() . '/' : '') . sprintf('UPGRADE-%s.md', $this->getMajorVersion($version));
    }

    /**
     * @internal
     */
    protected function getTargetNextMajorUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getUpgradeDir() . '/' : '') . sprintf('UPGRADE-%s.md', $this->getNextMajorVersion($version));
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

                $issues = $this->validator->validate($definition);
                if ($issues->count()) {
                    $messages = array_map(static fn (ConstraintViolationInterface $violation) => $violation->getMessage(), iterator_to_array($issues));

                    throw new \InvalidArgumentException(sprintf('Invalid file at path: %s, errors: %s', $file->getRealPath(), implode(', ', $messages)));
                }

                $featureFlagDefaultOn = false;

                if ($definition->getFlag()) {
                    $featureFlagDefaultOn = $this->featureFlags[$definition->getFlag()]['default'] ?? false;
                }

                if (!$featureFlagDefaultOn && !$includeFeatureFlags && $definition->getFlag()) {
                    continue;
                }

                $changelog = (new ChangelogFile())
                    ->setName($file->getFilename())
                    ->setPath((string) $file->getRealPath())
                    ->setDefinition($definition);

                $entries->add($changelog);
            }
        }

        return $entries;
    }

    private function getPlatformRoot(): string
    {
        if (!isset($this->platformRoot)) {
            $platformRoot = $this->projectDir;
            $composerJson = json_decode((string) file_get_contents($this->projectDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

            if ($composerJson === null || $composerJson['name'] !== 'shopware/platform') {
                $platformRoot .= '/platform';
            }

            $this->platformRoot = $platformRoot;
        }

        return $this->platformRoot;
    }
}
