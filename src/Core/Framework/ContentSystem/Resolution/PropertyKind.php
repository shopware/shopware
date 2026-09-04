<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum PropertyKind: string
{
    case Primitive = 'primitive';
    case Reference = 'reference';
}
