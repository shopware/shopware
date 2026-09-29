<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub;

use Danger\Struct\File;

/**
 * @internal
 */
class StubFile extends File
{
    public function __construct(
        string $name,
        string $status = self::STATUS_MODIFIED,
        private readonly string $content = '',
        string $patch = '',
        int $additions = 0,
        int $deletions = 0,
    ) {
        $this->name = $name;
        $this->status = $status;
        $this->patch = $patch;
        $this->additions = $additions;
        $this->deletions = $deletions;
        $this->changes = $additions + $deletions;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
