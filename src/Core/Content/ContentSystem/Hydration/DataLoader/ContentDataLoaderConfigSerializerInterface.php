<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
interface ContentDataLoaderConfigSerializerInterface
{
    /**
     * @return non-empty-string
     */
    public static function getSource(): string;

    /**
     * @param array<string, mixed> $data
     */
    public function decode(array $data): ContentDataLoaderConfigInterface;

    /**
     * @return array<string, mixed>
     */
    public function encode(ContentDataLoaderConfigInterface $config): array;
}
