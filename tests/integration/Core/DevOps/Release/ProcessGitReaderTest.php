<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\Release;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Release\ProcessGitReader;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Exercises {@see ProcessGitReader} against a real throwaway git repository, since its whole job is
 * to shell out to the git binary.
 *
 * @internal
 */
#[Package('framework')]
class ProcessGitReaderTest extends TestCase
{
    private string $repository;

    private Filesystem $filesystem;

    private ProcessGitReader $reader;

    private string $firstCommit;

    private string $secondCommit;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->repository = sys_get_temp_dir() . '/sw-git-reader-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->repository);

        $this->git(['git', '-c', 'init.defaultBranch=main', 'init']);

        // Root commit, so the commits under test have a parent and `git diff-tree` reports their changes.
        $this->filesystem->dumpFile($this->repository . '/README.md', "init\n");
        $this->commit('init');

        $this->filesystem->dumpFile($this->repository . '/RELEASE_INFO-6.7.md', "# 6.7.11.0\n\n### Feature A\n");
        $this->firstCommit = $this->commit('add release info');

        $this->filesystem->dumpFile($this->repository . '/RELEASE_INFO-6.7.md', "# 6.7.11.0\n\n### Feature A\n### Feature B\n");
        $this->filesystem->dumpFile($this->repository . '/src/Feature.php', "<?php\n");
        $this->secondCommit = $this->commit('add feature B and code');

        // A release branch that stops at the first commit, so the second commit is not reachable from it.
        $this->git(['git', 'branch', 'release', $this->firstCommit]);

        $this->reader = new ProcessGitReader($this->repository);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->repository);
    }

    public function testRefExists(): void
    {
        static::assertTrue($this->reader->refExists('main'));
        static::assertTrue($this->reader->refExists('release'));
        static::assertFalse($this->reader->refExists('does-not-exist'));
    }

    public function testShowFileReturnsContentAtRefAndEmptyForMissingPath(): void
    {
        static::assertStringContainsString('### Feature B', $this->reader->showFile('main', 'RELEASE_INFO-6.7.md'));
        static::assertStringNotContainsString('### Feature B', $this->reader->showFile('release', 'RELEASE_INFO-6.7.md'));
        static::assertSame('', $this->reader->showFile('main', 'does/not/exist.md'));
    }

    public function testFindIntroducingCommitLocatesThePickaxeMatch(): void
    {
        static::assertSame($this->secondCommit, $this->reader->findIntroducingCommit('main', '### Feature B', 'RELEASE_INFO-6.7.md'));
        static::assertSame('', $this->reader->findIntroducingCommit('main', '### Never written', 'RELEASE_INFO-6.7.md'));
    }

    public function testIsAncestor(): void
    {
        static::assertTrue($this->reader->isAncestor($this->firstCommit, 'main'));
        static::assertTrue($this->reader->isAncestor($this->firstCommit, 'release'));
        static::assertFalse($this->reader->isAncestor($this->secondCommit, 'release'));
    }

    public function testChangedFiles(): void
    {
        static::assertSame(['RELEASE_INFO-6.7.md'], $this->reader->changedFiles($this->firstCommit));
        static::assertSame(['RELEASE_INFO-6.7.md', 'src/Feature.php'], $this->reader->changedFiles($this->secondCommit));
    }

    private function commit(string $message): string
    {
        $this->git(['git', 'add', '-A']);
        $this->git([
            'git',
            '-c', 'user.email=test@example.com',
            '-c', 'user.name=Test',
            '-c', 'commit.gpgsign=false',
            'commit', '-m', $message,
        ]);

        return trim($this->git(['git', 'rev-parse', 'HEAD']));
    }

    /**
     * @param list<string> $command
     */
    private function git(array $command): string
    {
        $process = new Process($command, $this->repository);
        $process->mustRun();

        return $process->getOutput();
    }
}
