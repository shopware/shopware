<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Exception;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 */
#[Package('core')]
class JwtCertificateGenerationException extends \RuntimeException
{
}
