<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Changelog;

use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;

interface ChangelogParserInterface
{
    /**
     * @throws PluginChangelogInvalidException
     */
    public function parseChangelog(string $path): array;
}
