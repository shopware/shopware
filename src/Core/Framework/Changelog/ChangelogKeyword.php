<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
enum ChangelogKeyword: string
{
    case ADDED = 'Added';
    case REMOVED = 'Removed';
    case CHANGED = 'Changed';
    case DEPRECATED = 'Deprecated';
}
