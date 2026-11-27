<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * SEO paths are matched literally against the percent-encoded request path,
 * so a path stored with raw non-ASCII characters (e.g. "Babyöl") could never
 * be resolved. New paths are stored encoded, this migration encodes the
 * existing ones (e.g. "Baby%C3%B6l").
 *
 * @internal
 */
#[Package('inventory')]
class Migration1794881748EncodeNonAsciiSeoPathInfo extends MigrationStep
{
    private const BATCH_SIZE = 500;

    private const MAX_LENGTH = 750;

    public function getCreationTimestamp(): int
    {
        return 1794881748;
    }

    public function update(Connection $connection): void
    {
        $lastId = '';

        do {
            /** @var array<string, string> $rows */
            $rows = $connection->fetchAllKeyValue(
                'SELECT `id`, `seo_path_info` FROM `seo_url`
                WHERE `id` > :lastId AND LENGTH(`seo_path_info`) <> CHAR_LENGTH(`seo_path_info`)
                ORDER BY `id`
                LIMIT ' . self::BATCH_SIZE,
                ['lastId' => $lastId]
            );

            foreach ($rows as $id => $seoPathInfo) {
                $lastId = (string) $id;
                $this->encode($connection, $lastId, $seoPathInfo);
            }
        } while (\count($rows) === self::BATCH_SIZE);
    }

    private function encode(Connection $connection, string $id, string $seoPathInfo): void
    {
        $encoded = (string) preg_replace_callback(
            '/[\x80-\xFF]+/',
            static fn (array $match): string => rawurlencode($match[0]),
            $seoPathInfo
        );

        // keep the row as it is when the encoded path does not fit the column
        if ($encoded === $seoPathInfo || mb_strlen($encoded) > self::MAX_LENGTH) {
            return;
        }

        try {
            $connection->executeStatement(
                'UPDATE `seo_url` SET `seo_path_info` = :encoded WHERE `id` = :id',
                ['encoded' => $encoded, 'id' => $id]
            );
        } catch (UniqueConstraintViolationException) {
            // the encoded path is already stored for this language and sales channel, keep both rows as they are
        }
    }
}
