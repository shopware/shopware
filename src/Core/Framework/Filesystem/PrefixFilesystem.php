<?php

declare(strict_types=1);
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

namespace Shopware\Core\Framework\Filesystem;

use League\Flysystem\FilesystemInterface;

class PrefixFilesystem extends AbstractFilesystem
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @param FilesystemInterface $filesystem
     * @param string              $prefix
     */
    public function __construct(FilesystemInterface $filesystem, string $prefix)
    {
        parent::__construct($filesystem);

        if (empty($prefix)) {
            throw new \InvalidArgumentException('The prefix must not be empty.');
        }

        $this->prefix = $this->normalizePrefix($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function stripPath(string $path): string
    {
        $prefix = rtrim($this->prefix, '/');
        $path = str_replace($prefix, '', $path);
        $path = ltrim($path, '/');

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function preparePath(string $path): string
    {
        return $this->prefix . $path;
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    private function normalizePrefix(string $prefix): string
    {
        return trim($prefix, '/') . '/';
    }
}
