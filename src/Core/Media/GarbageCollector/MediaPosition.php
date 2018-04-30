<?php declare(strict_types=1);
/**
 * Shopware 5
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
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Media\GarbageCollector;

class MediaPosition
{
    /**
     * Different table type values
     */
    public const PARSE_PLAIN = 1;
    public const PARSE_JSON = 2;
    public const PARSE_SERIALIZE = 3;
    public const PARSE_HTML = 4;
    public const PARSE_PIPES = 5;

    /**
     * @var string
     */
    private $sourceTable;

    /**
     * @var string
     */
    private $sourceColumn;

    /**
     * @var string
     */
    private $mediaColumn;

    /**
     * @var int
     */
    private $parseType;

    /**
     * @param string $sourceTable  The source table where images are used. e.g. s_articles_img
     * @param string $sourceColumn The source column of the source table. e.g. media_id
     * @param string $mediaColumn  The matching column in the `s_media` table. Defaults to `id`.
     * @param int    $parseType    Defines the parse type. e.g. plain, json, html, serialized data
     */
    public function __construct($sourceTable, $sourceColumn, $mediaColumn = 'id', $parseType = self::PARSE_PLAIN)
    {
        $this->sourceTable = $sourceTable;
        $this->sourceColumn = $sourceColumn;
        $this->mediaColumn = $mediaColumn;
        $this->parseType = $parseType;
    }

    /**
     * @return string The source table where images are used. e.g. s_articles_img
     */
    public function getSourceTable(): string
    {
        return $this->sourceTable;
    }

    /**
     * @return string The source column of the source table. e.g. media_id
     */
    public function getSourceColumn(): string
    {
        return $this->sourceColumn;
    }

    /**
     * @return string the matching column in the `s_media` table
     */
    public function getMediaColumn(): string
    {
        return $this->mediaColumn;
    }

    /**
     * @return int Defines the parse type. e.g. plain, json, html, serialized data
     */
    public function getParseType(): int
    {
        return $this->parseType;
    }
}
