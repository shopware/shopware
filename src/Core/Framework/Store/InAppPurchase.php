<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
final class InAppPurchase implements ResetInterface
{
    /**
     * @var ?array<string, list<string>>
     */
    private ?array $activePurchases = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly InAppPurchaseProvider $inAppPurchaseProvider
    ) {
    }

    /**
     * @return list<string>
     */
    public function formatPurchases(): array
    {
        $activePurchases = $this->getActivePurchases();

        $formatted = [];
        foreach ($activePurchases as $extensionName => $purchases) {
            foreach ($purchases as $identifier) {
                $formatted[] = $extensionName . '-' . $identifier;
            }
        }

        return $formatted;
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->getActivePurchases();
    }

    /**
     * @return list<string>
     */
    public function getByExtension(string $extensionName): array
    {
        $activePurchases = $this->getActivePurchases();

        return $activePurchases[$extensionName] ?? [];
    }

    public function getJWTByExtension(string $extensionName): ?string
    {
        return $this->inAppPurchaseProvider->getPurchasesJWT()[$extensionName] ?? null;
    }

    public function reset(): void
    {
        $this->activePurchases = null;
    }

    public function isActive(string $extensionName, string $identifier): bool
    {
        $activePurchases = $this->getActivePurchases();

        return \in_array($identifier, $activePurchases[$extensionName] ?? [], true);
    }

    /**
     * @return array<string, list<string>>
     */
    private function getActivePurchases(): array
    {
        if ($this->activePurchases !== null) {
            return $this->activePurchases;
        }

        return $this->activePurchases = $this->inAppPurchaseProvider->getPurchases();
    }
}
