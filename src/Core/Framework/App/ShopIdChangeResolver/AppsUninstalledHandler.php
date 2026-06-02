<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Port implemented outside Core (e.g. Storefront) to react to apps being silently uninstalled by the
 * uninstall-apps shop-id-change strategy. Called with the live app entities BEFORE they are deleted, so
 * implementations still have everything they need (e.g. technical names for theme cleanup). Core only
 * knows this contract; it does not know who implements it.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface AppsUninstalledHandler
{
    public function uninstalled(AppCollection $apps, Context $context): void;
}
