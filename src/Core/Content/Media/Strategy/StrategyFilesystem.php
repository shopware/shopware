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

namespace Shopware\Content\Media\Strategy;

use League\Flysystem\FilesystemInterface;
use Shopware\Framework\Filesystem\AbstractFilesystem;

class StrategyFilesystem extends AbstractFilesystem
{
    /**
     * @var StrategyInterface
     */
    private $strategy;

    /**
     * @param FilesystemInterface $filesystem
     * @param StrategyInterface   $strategy
     */
    public function __construct(FilesystemInterface $filesystem, StrategyInterface $strategy)
    {
        parent::__construct($filesystem);

        $this->strategy = $strategy;
    }

    public function preparePath(string $path): string
    {
        return $this->strategy->encode($path);
    }

    public function stripPath(string $path): string
    {
        return $this->strategy->decode($path);
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        throw new \RuntimeException('Calling listContents() on a StrategyFilesystem is not supported.');
    }
}
