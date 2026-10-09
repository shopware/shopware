<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface ShopIdChangeStrategy
{
    public function getName(): string;

    /**
     * @return string the description of the strategy used to explain what the strategy does in CLI and API
     *
     * Note: in the administration we have separate snippets for this to localize the description, keep the descriptions in sync
     * `sw-app.component.sw-app-shop-id-change-modal.strategies.${strategy-name}.description`
     */
    public function getDescription(): string;

    public function resolve(Context $context): void;
}
