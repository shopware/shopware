<?php

declare(strict_types=1);
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

namespace Shopware\Filesystem;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\Handler;
use League\Flysystem\PluginInterface;

abstract class AbstractFilesystem implements FilesystemInterface
{
    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @param FilesystemInterface $filesystem
     */
    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->has($path);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->read($path);
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->readStream($path);
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->preparePath($directory);

        return array_map(
            function ($info) {
                $info['dirname'] = $this->stripPath($info['dirname']);
                $info['path'] = $this->stripPath($info['path']);

                return $info;
            },
            $this->filesystem->listContents($directory, $recursive)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $path = $this->preparePath($path);

        $meta = $this->filesystem->getMetadata($path);
        $meta['path'] = $this->stripPath($meta['path']);

        if (array_key_exists('dirname', $meta)) {
            $meta['dirname'] = $this->stripPath($meta['dirname']);
        }

        return $meta;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->getSize($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->getMimetype($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->getTimestamp($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->getVisibility($path);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->write($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->writeStream($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->update($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->updateStream($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        $path = $this->preparePath($path);
        $newpath = $this->preparePath($newpath);

        return $this->filesystem->rename($path, $newpath);
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        $path = $this->preparePath($path);
        $newpath = $this->preparePath($newpath);

        return $this->filesystem->copy($path, $newpath);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->delete($path);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        $dirname = $this->preparePath($dirname);

        return $this->filesystem->deleteDir($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, array $config = [])
    {
        $dirname = $this->preparePath($dirname);

        return $this->filesystem->createDir($dirname, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->setVisibility($path, $visibility);
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->put($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function putStream($path, $resource, array $config = [])
    {
        $path = $this->preparePath($path);

        return $this->filesystem->putStream($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function readAndDelete($path)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->readAndDelete($path);
    }

    /**
     * {@inheritdoc}
     */
    public function get($path, Handler $handler = null)
    {
        $path = $this->preparePath($path);

        return $this->filesystem->get($path, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function addPlugin(PluginInterface $plugin)
    {
        throw new \RuntimeException('Filesystem plugins are not allowed in abstract filesystems.');
    }

    public function getAdapter(): FilesystemInterface
    {
        return $this->filesystem;
    }

    /**
     * Modify the path before it will be passed to the filesystem
     *
     * @param string $path
     *
     * @return string
     */
    abstract public function preparePath(string $path): string;

    /**
     * Remove the modified parts from the filesystem
     *
     * @param string $path
     *
     * @return string
     */
    abstract public function stripPath(string $path): string;
}
