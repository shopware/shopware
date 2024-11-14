<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class BinaryType extends MediaType
{
    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name = 'BINARY';
}
