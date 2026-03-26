<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class RenderData implements \JsonSerializable
{
    // allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;
}
