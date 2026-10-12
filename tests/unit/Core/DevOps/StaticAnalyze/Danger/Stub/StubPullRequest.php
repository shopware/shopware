<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub;

use Danger\Struct\CommentCollection;
use Danger\Struct\CommitCollection;
use Danger\Struct\File;
use Danger\Struct\FileCollection;
use Danger\Struct\PullRequest;

/**
 * @internal
 */
class StubPullRequest extends PullRequest
{
    private readonly FileCollection $files;

    /**
     * @param list<File> $files the files changed by the pull request
     * @param array<string, string> $repositoryFiles path => content of the target repository state, served by getFile()/getFileContent()
     * @param list<string> $labels
     */
    public function __construct(
        array $files = [],
        private readonly array $repositoryFiles = [],
        array $labels = [],
    ) {
        $this->files = new FileCollection();
        foreach ($files as $file) {
            $this->files->set($file->name, $file);
        }

        $this->id = '1';
        $this->projectIdentifier = 'shopware/shopware';
        $this->title = 'Test pull request';
        $this->body = '';
        $this->labels = $labels;
        $this->createdAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->updatedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    public function getFiles(): FileCollection
    {
        return $this->files;
    }

    public function getFile(string $fileName): File
    {
        return new StubFile($fileName, File::STATUS_MODIFIED, $this->repositoryFiles[$fileName] ?? '');
    }

    public function getFileContent(string $path): string
    {
        return $this->repositoryFiles[$path] ?? '';
    }

    public function getCommits(): CommitCollection
    {
        return new CommitCollection();
    }

    public function getComments(): CommentCollection
    {
        return new CommentCollection();
    }
}
