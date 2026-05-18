<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Exception;

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

    public static function fileNotFound(string $fileId): self
    {
        return new self(\sprintf('Import/export v2 file "%s" could not be found.', $fileId));
    }

    public static function invalidPath(string $path): self
    {
        return new self(\sprintf('Import/export v2 path "%s" is invalid.', $path));
    }

    public static function invalidFormatContent(string $format, string $message): self
    {
        return new self(\sprintf('Import/export v2 %s content is invalid: %s', $format, $message));
    }

    public static function invalidExportFilter(string $path, string $message): self
    {
        return new self(\sprintf('Import/export v2 export filter "%s" is invalid: %s', $path, $message));
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

    public static function invalidImportRecord(string $message): self
    {
        return new self(\sprintf('Import/export v2 import record is invalid: %s', $message));
    }

    public static function invalidRequestParameter(string $parameter): self
    {
        return new self(\sprintf('Import/export v2 request parameter "%s" is invalid.', $parameter));
    }
}
