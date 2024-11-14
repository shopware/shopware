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

    public function findLastestTag(): ?string
    {
        $result = shell_exec('gh release list -R shopware/shopware --exclude-drafts --exclude-pre-releases --json tagName,isLatest');
        if (!$result) {
            return null;
        }

        $releases = json_decode($result, true) ?: [];
        foreach ($releases as $release) {
            if ($release['isLatest']) {
                return $release['tagName'];
            }
        }

        return null;
    }

    /**
     * @return list<array{headline: string, fixes: list<non-falsy-string>, author?: array{login: string}}>
     */
    public function getFixCommits(string $fromRef): array
    {
        $cmd = \sprintf(
            'git log --no-merges @ %s --pretty=format:%s -i -E --grep=%s',
            escapeshellarg('^' . $fromRef),
            escapeshellarg('%h'),
            escapeshellarg(ChangelogParser::FIXES_REGEX)
        );

        $fixes = [];

        exec($cmd, $refs);
        foreach ($refs as $ref) {
            $body = shell_exec('git log -n1 --pretty=format:%B ' . escapeshellarg($ref));

            if ($body && preg_match_all('/' . ChangelogParser::FIXES_REGEX . '/i', $body, $matches)) {
                $fix = [
                    'headline' => strtok($body, "\n") ?: '',
                    'fixes' => $matches[3],
                ];
                $author = $this->findAuthor($matches[3][0]);
                if ($author && !$this->isShopwareOrgMember($author['login'])) {
                    $fix['author'] = $author;
                }

                $fixes[] = $fix;
            }
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
            throw new \InvalidArgumentException('Unable to generate next version number, supplied version seems invalid (' . $version . ')');
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
                    $messages = \array_map(static fn (ConstraintViolationInterface $violation) => $violation->getMessage(), \iterator_to_array($violations));

                    throw new \InvalidArgumentException(\sprintf('Invalid file at path: %s, errors: %s', $file->getRealPath(), \implode(', ', $messages)));
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
