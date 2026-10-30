<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Log\Package;

/**
 * Who acts: a user, an integration, or an app. An app acts through its integration, so an integration that belongs to
 * an app is the App. An API caller is only ever a User or an Integration.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum IdentityType
{
    case User;
    case Integration;
    case App;
}
