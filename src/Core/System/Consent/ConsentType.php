<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

#[Package('data-services')]
enum ConsentType: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case APP = 'app';
}
