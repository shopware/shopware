<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class BinaryType extends MediaType
{
    protected string $name = 'BINARY';
}
