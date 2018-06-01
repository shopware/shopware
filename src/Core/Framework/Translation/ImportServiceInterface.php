<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Symfony\Component\Finder\SplFileInfo;

interface ImportServiceInterface
{
    public function import(array $paths, bool $truncateBeforeImport, string $tenantId): void;

    public function importFile(SplFileInfo $file, string $tenantId): void;
}
