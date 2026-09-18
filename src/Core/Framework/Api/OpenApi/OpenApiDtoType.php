<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
enum OpenApiDtoType
{
    case Request;
    case Response;
    case Nested;
}
