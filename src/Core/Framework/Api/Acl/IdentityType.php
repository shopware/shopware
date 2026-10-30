<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Log\Package;

/**
 * Who acts: a user, an integration, or an app through its manifest.
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
