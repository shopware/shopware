<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Specifies the type of content layout being rendered.
 *
 * Header and footer layouts use domain-aware resolution (Domain → SalesChannel → Global),
 * while main content (Product/Category/LandingPage) uses sales channel resolution.
 */
#[Package('discovery')]
enum LayoutType: string
{
    case HEADER = 'header';
    case FOOTER = 'footer';
    case MAIN = 'main';
}
