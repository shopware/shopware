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

namespace Shopware\Core\Content\Media\Util\Strategy;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;

class Md5Strategy implements StrategyInterface
{
    /**
     * @var array
     */
    private $blacklist = [
        'ad' => 'g0',
    ];

    /**
     * {@inheritdoc}
     */
    public function decode(string $path): string
    {
        return basename($path);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(string $filename): string
    {
        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        if ($this->isEncoded($filename)) {
            return $filename;
        }

        $md5hash = md5($filename);

        $md5hashSlices = array_slice(str_split($md5hash, 2), 0, 3);
        $md5hashSlices = array_map(
            function ($slice) {
                return array_key_exists($slice, $this->blacklist) ? $this->blacklist[$slice] : $slice;
            },
            $md5hashSlices
        );

        return implode('/', $md5hashSlices) . '/' . $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function isEncoded(string $path): bool
    {
        if ($this->hasBlacklistParts($path)) {
            return false;
        }

        return (bool) preg_match("/(?:([0-9a-g]{2}\/[0-9a-g]{2}\/[0-9a-g]{2}\/))((.+)\.(.+))/", $path);
    }

    /**
     * Name of the strategy
     */
    public function getName(): string
    {
        return 'md5';
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function hasBlacklistParts(string $path): bool
    {
        foreach ($this->blacklist as $key => $value) {
            if (strpos($path, '/' . $key . '/') !== false) {
                return true;
            }
        }

        return false;
    }
}
