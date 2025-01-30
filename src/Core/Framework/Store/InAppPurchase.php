<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
final class InAppPurchase implements ResetInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $activePurchases = [];

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
        $this->ensureRegistration();

        $formatted = [];
        foreach ($this->activePurchases as $extensionName => $purchases) {
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
        $this->ensureRegistration();

        return $this->activePurchases;
    }

    /**
     * @return list<string>
     */
    public function getByExtension(string $extensionName): array
    {
        $this->ensureRegistration();

        return $this->activePurchases[$extensionName] ?? [];
    }

    public function getJWTByExtension(string $extensionName): ?string
    {
        return $this->inAppPurchaseProvider->getPurchasesJWT()[$extensionName] ?? null;
    }

    public function reset(): void
    {
        $this->activePurchases = [];
    }

    public function isActive(string $extensionName, string $identifier): bool
    {
        $this->ensureRegistration();

        return \in_array($identifier, $this->activePurchases[$extensionName] ?? [], true);
    }

    private function ensureRegistration(): void
    {
        if (\count($this->activePurchases)) {
            return;
        }

        $this->activePurchases = $this->inAppPurchaseProvider->getPurchases();
    }
}
