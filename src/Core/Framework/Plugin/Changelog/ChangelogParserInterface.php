<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

interface ChangelogParserInterface
{
    public function parseChangelog(string $path): array;
}
