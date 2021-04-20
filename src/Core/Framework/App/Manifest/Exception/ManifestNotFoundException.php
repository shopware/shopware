<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Exception;

/**
 * @internal only for use by the app-system
 */
class ManifestNotFoundException extends \RuntimeException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf(
            'No "manifest.xml" file in path "%s" found. (The file must be placed in the app root folder.)',
            $path
        ), 0, $previous);
    }
}
