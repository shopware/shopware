<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogException;
use Shopware\Core\Framework\Changelog\ChangelogFile;
use Shopware\Core\Framework\Changelog\ChangelogFileCollection;
use Shopware\Core\Framework\Changelog\ChangelogParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
class ChangelogProcessor
{
    private ?string $platformRoot = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $users = null;

    /**
     * @param array<string, FeatureFlagConfig> $featureFlags
     */
    public function __construct(
        protected ChangelogParser $parser,
        protected ValidatorInterface $validator,
        protected Filesystem $filesystem,
        private readonly string $projectDir,
        protected array $featureFlags,
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

    public function findLastestTag(?string $version = null): ?string
    {
        if ($version === null || !preg_match('/^v?(\d+)\.(\d+)\./', $version, $matches)) {
            return null;
        }

        // Find the latest release tag on the same major.minor line that is reachable from HEAD.
        // GitHub's global "isLatest" flag cannot be used here, as it points to the latest release
        // across all branches (e.g. a 6.7.x tag while releasing 6.6.x), which is not an ancestor
        // of the current branch and would yield a wrong commit range.
        $tag = shell_exec(\sprintf(
            'git -C %s describe --tags --abbrev=0 --match %s @ 2>/dev/null',
            escapeshellarg($this->platformRoot ?? $this->projectDir),
            escapeshellarg(\sprintf('v%d.%d.*', (int) $matches[1], (int) $matches[2]))
        ));

        $tag = $tag ? trim($tag) : '';

        return $tag !== '' ? $tag : null;
    }

    /**
     * @return list<array{headline: string, fixes: list<non-falsy-string>, author?: array{login: string}}>
     */
    public function getFixCommits(string $fromRef): array
    {
        $root = $this->platformRoot ?? $this->projectDir;

        $cmd = \sprintf(
            'git -C %s log --no-merges @ %s --pretty=format:%s -E --grep=%s',
            escapeshellarg($root),
            escapeshellarg('^' . $fromRef),
            escapeshellarg('%h'),
            escapeshellarg(ChangelogParser::RELEVANT_COMMIT_REGEX)
        );

        $fixes = [];

        exec($cmd, $refs);
        foreach ($refs as $ref) {
            $subject = shell_exec(\sprintf(
                'git -C %s log -n1 --pretty=format:%s %s',
                escapeshellarg($root),
                escapeshellarg('%s'),
                escapeshellarg($ref)
            ));
            $subject = $subject ? trim($subject) : '';

            if ($subject === '' || !preg_match('/' . ChangelogParser::RELEVANT_COMMIT_REGEX . '/', $subject, $matches)) {
                continue;
            }

            $pullRequest = '#' . $matches[3];

            $fix = [
                // Drop the trailing "(#12345)" reference; it is rendered separately as the link target.
                'headline' => trim((string) preg_replace('/\s*\(#[0-9]+\)\s*$/', '', $subject)),
                'fixes' => [$pullRequest],
            ];
            $author = $this->findAuthor($pullRequest);
            if ($author && !$this->isShopwareOrgMember($author['login'])) {
                $fix['author'] = $author;
            }

            $fixes[] = $fix;
        }

        return $fixes;
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
            throw ChangelogException::invalidVersion($version);
        }

        $superVersion = (int) $superVersion;
        $majorVersion = (int) $majorVersion;

        return $superVersion . '.' . ($majorVersion + 1);
    }

    protected function getTargetUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getUpgradeDir() . '/' : '') . \sprintf('UPGRADE-%s.md', $this->getMajorVersion($version));
    }

    /**
     * @internal
     */
    protected function getTargetNextMajorUpgradeFile(string $version, bool $realPath = true): string
    {
        return ($realPath ? $this->getUpgradeDir() . '/' : '') . \sprintf('UPGRADE-%s.md', $this->getNextMajorVersion($version));
    }

    /**
     * Prepare the list of changelog files which need to process
     */
    protected function prepareChangelogFiles(?string $version = null, bool $includeFeatureFlags = false): ChangelogFileCollection
    {
        $entries = new ChangelogFileCollection();

        $issueKeys = [];

        $finder = new Finder();
        $rootDir = $version ? $this->getTargetReleaseDir($version) : $this->getUnreleasedDir();
        $finder->in($rootDir)->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $definition = $this->parser->parse($file, $rootDir);

                $violations = $this->validator->validate($definition);
                if ($violations->count()) {
                    $messages = \array_map(static fn (ConstraintViolationInterface $violation) => (string) $violation->getMessage(), \iterator_to_array($violations));

                    throw ChangelogException::invalidChangelogFile((string) $file->getRealPath(), array_values($messages));
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

                $issueKeys[$definition->getIssue()] = $definition->getIssue();
            }
        }

        return $entries;
    }

    /**
     * @return array{login: string}
     */
    private function findAuthor(string $issueId): ?array
    {
        $result = shell_exec(\sprintf('gh pr view https://github.com/shopware/shopware/pull/%s --json author', escapeshellarg(ltrim($issueId, '#'))));

        if ($result) {
            return json_decode($result, true)['author'] ?? null;
        }

        return null;
    }

    private function isShopwareOrgMember(string $login): bool
    {
        if ($this->users === null) {
            $this->users = [];
            $result = shell_exec('gh api --paginate -H "Accept: application/vnd.github+json" -H "X-GitHub-Api-Version: 2022-11-28" /orgs/shopware/members');
            if ($result) {
                /** @var array<array{login: string}> */
                $data = json_decode($result, true);

                foreach ($data as $member) {
                    $this->users[$member['login']] = $member;
                }
            }
        }

        return isset($this->users[$login]);
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
