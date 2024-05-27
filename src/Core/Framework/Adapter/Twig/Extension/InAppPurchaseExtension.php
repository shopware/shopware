<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('checkout')]
class InAppPurchaseExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('inAppPurchase', $this->isActive(...)),
            new TwigFunction('allInAppPurchases', $this->all(...)),
        ];
    }

    public function isActive(string $identifier): bool
    {
        return InAppPurchase::isActive($identifier);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return InAppPurchase::all();
    }
}
