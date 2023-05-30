<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @deprecated tag:v6.6.0 - Will be removed, as the bundle was not used anymore
 */
#[Package('core')]
class Filesystem extends Bundle
{
}
