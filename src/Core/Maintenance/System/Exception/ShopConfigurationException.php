<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ShopConfigurationException extends \RuntimeException
{
}
