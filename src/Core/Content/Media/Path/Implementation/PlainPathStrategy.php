<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
class PlainPathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'plain';
    }
}
