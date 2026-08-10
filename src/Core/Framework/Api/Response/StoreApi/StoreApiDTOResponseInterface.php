<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker interface implemented by every generated Store API response DTO so that
 * these classes can be identified as responses in later processing steps.
 */
#[Package('framework')]
interface StoreApiDTOResponseInterface
{
}
