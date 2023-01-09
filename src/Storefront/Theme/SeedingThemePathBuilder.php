<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @package storefront
 */
class SeedingThemePathBuilder extends AbstractThemePathBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection
    ) {
    }

    public function assemblePath(string $salesChannelId, string $themeId): string
    {
        return $this->generateNewPath($salesChannelId, $themeId, $this->getSeed($salesChannelId, $themeId));
    }

    public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
    {
        return md5($themeId . $salesChannelId . $seed);
    }

    public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
    {
        $this->connection->executeStatement(
            'UPDATE `theme_sales_channel` SET `hash` = :hash WHERE `sales_channel_id` = :salesChannelId AND `theme_id` = :themeId',
            [
                'hash' => $seed,
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'themeId' => Uuid::fromHexToBytes($themeId),
            ]
        );
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    private function getSeed(string $salesChannelId, string $themeId): string
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest && $mainRequest->attributes->has(SalesChannelRequest::ATTRIBUTE_THEME_HASH)) {
            return $mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_HASH);
        }

        // This fallback is only needed for admin requests and CLI, for storefront requests the hash is already set in the RequestTransformer
        /** @var string|false|null $hash */
        $hash = $this->connection->fetchOne(
            'SELECT `hash` FROM `theme_sales_channel` WHERE `theme_id` = :themeId AND `sales_channel_id` = :salesChannelId',
            ['themeId' => Uuid::fromHexToBytes($themeId), 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        if (!$hash) {
            return '';
        }

        return $hash;
    }
}
