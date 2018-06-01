<?php declare(strict_types=1);
/**
 * Shopware\Core 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Content\Media\Util\GarbageCollector;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Media;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;

/**
 * Class GarbageCollectorFactory
 */
class GarbageCollectorFactory
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var StrategyInterface
     */
    private $strategy;

    /**
     * @param Connection        $connection
     * @param StrategyInterface $strategy
     */
    public function __construct(Connection $connection, StrategyInterface $strategy)
    {
        $this->connection = $connection;
        $this->strategy = $strategy;
    }

    /**
     * @return GarbageCollector
     */
    public function factory(): GarbageCollector
    {
        $mediaPositions = $this->getMediaPositions();

        return new GarbageCollector($mediaPositions, $this->connection, $this->strategy);
    }

    /**
     * Return default media-positions
     *
     * @return MediaPosition[]
     */
    private function getDefaultMediaPositions(): array
    {
        return [
            new MediaPosition('s_articles_img', 'media_id'),
            new MediaPosition('s_categories', 'mediaID'),
            new MediaPosition('s_emarketing_banners', 'img', 'path'),
            new MediaPosition('s_blog_media', 'media_id'),
            new MediaPosition('s_core_config_mails_attachments', 'mediaID'),
            new MediaPosition('s_filter_values', 'media_id'),
            new MediaPosition('s_emotion_element_value', 'value', 'path'),
            new MediaPosition('s_emotion_element_value', 'value', 'path', MediaPosition::PARSE_JSON),
            new MediaPosition('s_emotion_element_value', 'value', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_articles_downloads', 'filename', 'path'),
            new MediaPosition('s_articles_supplier', 'img', 'path'),
            new MediaPosition('s_core_templates_config_values', 'value', 'path', MediaPosition::PARSE_SERIALIZE),
            new MediaPosition('s_core_documents_box', 'value', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_articles', 'description_long', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_billing_template', 'value', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_campaigns_html', 'html', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_cms_static', 'html', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_cms_support', 'text', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_cms_support', 'text2', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_core_config_mails', 'contentHTML', 'path', MediaPosition::PARSE_HTML),
            new MediaPosition('s_core_config_values', 'value', 'path', MediaPosition::PARSE_SERIALIZE),
        ];
    }

    /**
     * @return MediaPosition[]
     */
    private function getMediaPositions(): array
    {
        $mediaPositions = new ArrayCollection(
            array_merge(
                $this->getDefaultMediaPositions(),
                $this->getAttributeMediaPositions()
            )
        );

//        $mediaPositions = $this->events->collect(
//            'Shopware_Collect_MediaPositions',
//            $mediaPositions
//        );

        return $mediaPositions->toArray();
    }

    /**
     * @return MediaPosition[]
     */
    private function getAttributeMediaPositions(): array
    {
        return [];
        $mediaPositions = [];

        //todo@dr tenant id missing
        // value is just the media ID
        $singleSelectionColumns = $this->connection->createQueryBuilder()
            ->select(['table_name', 'column_name'])
            ->from('attribute_configuration')
            ->andWhere('entity = :entityName')
            ->andWhere('column_type = :columnType')
            ->setParameters([
                'entityName' => Media::class,
                'columnType' => 'single_selection',
            ])
            ->execute()
            ->fetchAll();

        foreach ($singleSelectionColumns as $attribute) {
            $mediaPositions[] = new MediaPosition($attribute['table_name'], $attribute['column_name']);
        }

        // values are separated by pipes '|'
        $multiSelectionColumns = $this->connection->createQueryBuilder()
            ->select(['table_name', 'column_name'])
            ->from('attribute_configuration')
            ->andWhere('entity = :entityName')
            ->andWhere('column_type = :columnType')
            ->setParameters([
                'entityName' => Media::class,
                'columnType' => 'multi_selection',
            ])
            ->execute()
            ->fetchAll();

        foreach ($multiSelectionColumns as $attribute) {
            $mediaPositions[] = new MediaPosition($attribute['table_name'], $attribute['column_name'], 'id', MediaPosition::PARSE_PIPES);
        }

        return $mediaPositions;
    }
}
