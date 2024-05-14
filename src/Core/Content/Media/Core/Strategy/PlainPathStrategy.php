<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Strategy;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
#[Package('buyers-experience')]
class PlainPathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'plain';
    }
}
