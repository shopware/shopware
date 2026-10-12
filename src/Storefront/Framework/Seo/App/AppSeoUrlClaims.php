<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlClaims
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string> normalized path => app name
     */
    public function pathsOfOtherApps(string $appName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `app_name`, `payload` FROM `app_feature` WHERE `type` = :type AND `app_name` != :appName',
            ['type' => SeoUrlAppFeatureDefinition::TYPE, 'appName' => $appName],
        );

        $claimedBy = [];

        foreach ($rows as $row) {
            $paths = Json::decodeToArray((string) $row['payload'])['paths'] ?? [];

            if (!\is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (\is_string($path)) {
                    $claimedBy[self::normalizePath($path)] = (string) $row['app_name'];
                }
            }
        }

        return $claimedBy;
    }

    /**
     * @return array<string, string> hook => app name
     */
    public function hooksOfOtherApps(string $appName): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `app_name`, `payload` FROM `app_feature` WHERE `type` IN (:types) AND `app_name` != :appName',
            [
                'types' => [SeoUrlAppFeatureDefinition::TYPE, EntitySeoUrlAppFeatureDefinition::TYPE],
                'appName' => $appName,
            ],
            ['types' => ArrayParameterType::STRING],
        );

        $claimedBy = [];

        foreach ($rows as $row) {
            $hook = Json::decodeToArray((string) $row['payload'])['hook'] ?? null;

            if (\is_string($hook)) {
                $claimedBy[$hook] = (string) $row['app_name'];
            }
        }

        return $claimedBy;
    }

    public static function normalizePath(string $path): string
    {
        return mb_strtolower(ltrim($path, '/'));
    }
}
