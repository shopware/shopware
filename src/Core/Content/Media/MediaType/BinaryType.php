<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class BinaryType extends MediaType
{
    protected $name = 'BINARY';
}
