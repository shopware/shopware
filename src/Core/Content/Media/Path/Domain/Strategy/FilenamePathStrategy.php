<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Domain\Strategy;

use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Path\Contract\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
#[Package('content')]
class FilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'file_name';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return $location instanceof ThumbnailLocationStruct ? $location->media->fileName : $location->fileName;
    }

    protected function blacklist(): array
    {
        return ['ad' => 'g0'];
    }
}
