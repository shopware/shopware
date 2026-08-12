<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Release;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * {@see GitReader} backed by the git binary via Symfony Process. Commands are passed as argument
 * lists (never a shell string), so there is no shell interpolation to escape and no shell_exec/exec.
 *
 * @internal
 *
 * @see \Shopware\Tests\Integration\Core\DevOps\Release\ProcessGitReaderTest
 */
#[Package('framework')]
class ProcessGitReader implements GitReader
{
    /**
     * @param string|null $workingDirectory directory the git commands run in; null uses the current
     *                                      working directory (the checked-out repository in CI)
     */
    public function __construct(
        private readonly ?string $workingDirectory = null,
    ) {
    }

    public function showFile(string $ref, string $path): string
    {
        // git show exits non-zero when the ref or file is absent; callers treat '' as "not present".
        return $this->output(['git', 'show', $ref . ':' . $path]);
    }

    public function refExists(string $ref): bool
    {
        return $this->succeeds(['git', 'rev-parse', '--verify', '--quiet', $ref]);
    }

    public function findIntroducingCommit(string $ref, string $needle, string $path): string
    {
        return trim($this->output(['git', 'log', $ref, '--format=%H', '--max-count=1', '-S', $needle, '--', $path]));
    }

    public function isAncestor(string $commit, string $ref): bool
    {
        return $this->succeeds(['git', 'merge-base', '--is-ancestor', $commit, $ref]);
    }

    public function changedFiles(string $commit): array
    {
        $output = trim($this->output(['git', 'diff-tree', '--no-commit-id', '-r', '--name-only', $commit]));

        return array_values(array_filter(explode("\n", $output), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param list<string> $command
     */
    private function output(array $command): string
    {
        $process = new Process($command, $this->workingDirectory);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : '';
    }

    /**
     * @param list<string> $command
     */
    private function succeeds(array $command): bool
    {
        $process = new Process($command, $this->workingDirectory);
        $process->run();

        return $process->isSuccessful();
    }
}
