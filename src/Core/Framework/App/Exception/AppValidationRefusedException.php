<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * Thrown when a manifest is refused. Carries the failing check's own error code, message and
 * parameters, so callers that only report validation problems can catch this without also catching
 * every other way an installation can fail.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppValidationRefusedException extends AppException
{
}
