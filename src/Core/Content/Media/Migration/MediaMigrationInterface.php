<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Migration;

interface MediaMigrationInterface
{
    public function run(bool $skipScan = false): void;

    public function migrateFile(string $path): void;

    public function countFiles(string $directory): int;
}
