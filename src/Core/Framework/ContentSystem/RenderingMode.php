<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Controls content rendering pipeline behavior.
 *
 * FULL: Complete pipeline - pre-hydration, hydration, post-hydration.
 * SKELETON: Skip hydration - returns layout structure without loaded data.
 */
#[Package('framework')]
enum RenderingMode: string
{
    case FULL = 'full';
    case SKELETON = 'skeleton';
}
