<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2Exception extends \RuntimeException
{
    public static function profileNotFound(string $name): self
    {
        return new self(\sprintf('Import/export v2 profile "%s" could not be found.', $name));
    }

    public static function formatNotFound(string $format): self
    {
        return new self(\sprintf('Import/export v2 format "%s" could not be found.', $format));
    }

    public static function runNotFound(string $runId): self
    {
        return new self(\sprintf('Import/export v2 run "%s" could not be found.', $runId));
    }

    public static function artifactNotFound(string $artifactId): self
    {
        return new self(\sprintf('Import/export v2 artifact "%s" could not be found.', $artifactId));
    }

    public static function invalidImportRecord(int $index, string $message): self
    {
        return new self(\sprintf('Import/export v2 record %d is invalid: %s', $index, $message));
    }

    public static function invalidPath(string $path): self
    {
        return new self(\sprintf('Import/export v2 path "%s" is invalid.', $path));
    }

    public static function invalidFormatContent(string $format, string $message): self
    {
        return new self(\sprintf('Import/export v2 %s content is invalid: %s', $format, $message));
    }

    public static function entityNotFound(string $entity, string $identifierName, string $identifierValue): self
    {
        return new self(\sprintf(
            'Import/export v2 could not find %s for %s "%s".',
            $entity,
            $identifierName,
            $identifierValue
        ));
    }

    public static function invalidRunState(string $runId, string $state, string $action): self
    {
        return new self(\sprintf(
            'Import/export v2 run "%s" cannot %s while in state "%s".',
            $runId,
            $action,
            $state
        ));
    }
}
