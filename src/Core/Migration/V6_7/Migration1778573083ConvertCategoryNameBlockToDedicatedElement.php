<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1778573083ConvertCategoryNameBlockToDedicatedElement extends MigrationStep
{
    private const ORIGINAL_STATIC_VALUE = '<h1>{{ category.name }}</h1>';

    public function getCreationTimestamp(): int
    {
        return 1778573083;
    }

    public function update(Connection $connection): void
    {
        $blocks = $connection->fetchAllAssociative(
            'SELECT id FROM cms_block WHERE name = :name AND type = :type AND locked = 1',
            ['name' => 'Category name', 'type' => 'text']
        );

        if (\count($blocks) === 0) {
            return;
        }

        $newConfig = json_encode([
            'content' => [
                'source' => 'mapped',
                'value' => 'category.name',
            ],
        ], \JSON_THROW_ON_ERROR);

        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        foreach ($blocks as $block) {
            $slots = $connection->fetchAllAssociative(
                'SELECT slot.id, translation.config
                FROM cms_slot slot
                INNER JOIN cms_slot_translation translation ON translation.cms_slot_id = slot.id
                    AND translation.cms_slot_version_id = slot.version_id
                    AND translation.language_id = :systemLanguageId
                WHERE slot.cms_block_id = :blockId
                AND slot.type = :type
                AND slot.slot = :slot',
                [
                    'blockId' => $block['id'],
                    'systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'type' => 'text',
                    'slot' => 'content',
                ]
            );

            $allSlotsUnmodified = true;
            foreach ($slots as $slot) {
                $config = json_decode((string) $slot['config'], true, 512, \JSON_THROW_ON_ERROR);
                $contentValue = $config['content']['value'] ?? null;
                $contentSource = $config['content']['source'] ?? null;

                if ($contentSource !== 'static' || $contentValue !== self::ORIGINAL_STATIC_VALUE) {
                    $allSlotsUnmodified = false;
                    break;
                }
            }

            if (!$allSlotsUnmodified) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE cms_block SET type = :newType WHERE id = :id',
                ['newType' => 'category-heading', 'id' => $block['id']]
            );

            $connection->executeStatement(
                'UPDATE cms_slot SET type = :newType WHERE cms_block_id = :blockId AND type = :oldType AND slot = :slot',
                [
                    'newType' => 'category-name',
                    'oldType' => 'text',
                    'blockId' => $block['id'],
                    'slot' => 'content',
                ]
            );

            $connection->executeStatement(
                'UPDATE cms_slot_translation translation
                INNER JOIN cms_slot slot ON slot.id = translation.cms_slot_id AND slot.version_id = translation.cms_slot_version_id
                SET translation.config = :config
                WHERE slot.cms_block_id = :blockId
                AND slot.type = :type
                AND slot.slot = :slot
                AND slot.version_id = :liveVersionId',
                [
                    'config' => $newConfig,
                    'blockId' => $block['id'],
                    'type' => 'category-name',
                    'slot' => 'content',
                    'liveVersionId' => $liveVersionId,
                ]
            );
        }
    }
}
