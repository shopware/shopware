<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
enum ReleaseEndpointsEnum: string
{
    case RELEASE = 'https://releases.shopware.com/changelog/index.json';
    case SECURITY = 'https://releases.shopware.com/changelog/security-release.json';
}
