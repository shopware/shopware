<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Params;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
enum UrlParamsSource
{
    case MEDIA;
    case THUMBNAIL;
}
