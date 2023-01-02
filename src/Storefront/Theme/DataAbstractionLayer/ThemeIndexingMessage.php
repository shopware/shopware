<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * @package storefront
 */
#[Package('storefront')]
class ThemeIndexingMessage extends EntityIndexingMessage
{
}
