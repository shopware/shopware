<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Path\Contract\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
class IdPathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'id';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return $location->id;
    }
}
