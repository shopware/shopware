<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed without replacement as the class where this exception is thrown will be removed
 *
 * @phpstan-ignore shopware.internalClass
 */
#[Package('core')]
class JwtCertificateGenerationException extends \RuntimeException
{
}
