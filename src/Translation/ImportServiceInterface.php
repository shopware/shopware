<?php

namespace Shopware\Translation;

use Symfony\Component\Finder\SplFileInfo;

interface ImportServiceInterface
{
    public function import(array $paths, bool $truncateBeforeImport): void;

    public function importFile(SplFileInfo $file): void;
}