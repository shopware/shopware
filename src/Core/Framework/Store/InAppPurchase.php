<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class InAppPurchase
{
    /**
     * @var array<string, string>
     */
    private static array $activePurchases = [];

    /**
     * @var array<string, list<string>>
     */
    private static array $extensionPurchases = [];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return \array_keys(self::$activePurchases);
    }

    /**
     * @return array<string, string>
     */
    public static function allPurchases(): array
    {
        return self::$activePurchases;
    }

    /**
     * @return list<string>
     */
    public static function getByExtension(string $extensionId): array
    {
        return self::$extensionPurchases[$extensionId] ?? [];
    }

    public static function reset(): void
    {
        self::$activePurchases = [];
        self::$extensionPurchases = [];
    }

    public static function isActive(string $identifier): bool
    {
        return \in_array($identifier, \array_keys(self::$activePurchases), true);
    }

    /**
     * @param array<string, string> $inAppPurchases
     */
    public static function registerPurchases(array $inAppPurchases = []): void
    {
        self::$activePurchases = $inAppPurchases;

        // group by extension id, which is the value of the array
        self::$extensionPurchases = [];

        foreach ($inAppPurchases as $identifier => $extensionId) {
            self::$extensionPurchases[$extensionId][] = $identifier;
        }
    }
}
