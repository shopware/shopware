<?php
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

namespace Shopware\MediaThumbnail\Struct;

use Shopware\Framework\Struct\Struct;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class MediaThumbnail extends Struct
{
    /**
     * @var string
     */
    protected $source;

    /**
     * @var string|null
     */
    protected $retinaSource;

    /**
     * @var int
     */
    protected $maxWidth;

    /**
     * @var int
     */
    protected $maxHeight;

    /**
     * @var string
     */
    protected $sourceSet;

    /**
     * @param string      $source
     * @param string|null $retinaSource
     * @param $maxWidth
     * @param $maxHeight
     */
    public function __construct($source, $retinaSource, $maxWidth, $maxHeight)
    {
        $this->source = $source;
        $this->retinaSource = $retinaSource;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;

        if ($this->hasRetinaSource()) {
            $this->sourceSet = sprintf('%s, %s 2x', $this->getSource(), $this->getRetinaSource());
        } else {
            $this->sourceSet = $this->getSource();
        }
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return bool
     */
    public function hasRetinaSource(): bool
    {
        return $this->retinaSource != null;
    }

    /**
     * @return null|string
     */
    public function getRetinaSource(): ?string
    {
        return $this->retinaSource;
    }

    /**
     * @return string
     */
    public function getSourceSet(): string
    {
        return $this->sourceSet;
    }

    /**
     * @return int
     */
    public function getMaxWidth(): int
    {
        return $this->maxWidth;
    }

    /**
     * @return int
     */
    public function getMaxHeight(): int
    {
        return $this->maxHeight;
    }
}
