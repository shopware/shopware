<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * @package core
 *
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "system"="Shopware\Core\Framework\Api\Context\SystemSource",
 *    "sales-channel"="Shopware\Core\Framework\Api\Context\SalesChannelApiSource",
 *    "admin-api"="Shopware\Core\Framework\Api\Context\AdminApiSource",
 *    "shop-api"="Shopware\Core\Framework\Api\Context\ShopApiSource",
 *    "admin-sales-channel-api"="Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource"
 * })
 */
interface ContextSource
{
}
