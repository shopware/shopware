<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.8.0 - Will be removed, use `AppException::cmsBlockFileMissing()` or `AppException::cmsBlockOutsideOfBlocksDirectory()` instead
 */
#[Package('framework')]
class AppCmsExtensionException extends \RuntimeException
{
}
