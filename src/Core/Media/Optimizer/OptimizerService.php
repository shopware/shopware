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

namespace Shopware\Media\Optimizer;

use Shopware\Media\Exception\OptimizerNotFoundException;
use Shopware\Media\Strategy\StrategyFilesystem;

class OptimizerService implements OptimizerServiceInterface
{
    /**
     * @var OptimizerInterface[]
     */
    private $optimizers;

    /**
     * @var StrategyFilesystem
     */
    private $filesystem;

    /**
     * @param OptimizerInterface[]|iterable               $optimizers
     * @param \Shopware\Media\Strategy\StrategyFilesystem $filesystem
     */
    public function __construct(iterable $optimizers, StrategyFilesystem $filesystem)
    {
        $this->optimizers = $optimizers;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function optimize(string $filename): void
    {
        $mimeType = $this->filesystem->getMimetype($filename);
        $optimizer = $this->getOptimizerByMimeType($mimeType);

        $tmpFilename = $this->download($filename);

        $optimizer->run($tmpFilename);

        $this->upload($filename, $tmpFilename);
        unlink($tmpFilename);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptimizers(): array
    {
        return $this->optimizers;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptimizerByMimeType(string $mimeType): OptimizerInterface
    {
        foreach ($this->optimizers as $optimizer) {
            if (in_array($mimeType, $optimizer->getSupportedMimeTypes(), true) && $optimizer->isRunnable()) {
                return $optimizer;
            }
        }

        throw new OptimizerNotFoundException(sprintf('Optimizer for mime-type "%s" not found.', $mimeType));
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    private function download(string $filename): string
    {
        $tmpFilename = tempnam(sys_get_temp_dir(), 'optimize_media_');
        $handle = fopen($tmpFilename, 'wb');

        stream_copy_to_stream(
            $this->filesystem->readStream($filename),
            $handle
        );

        return $tmpFilename;
    }

    /**
     * @param string $filename
     * @param string $tmpFilename
     */
    private function upload(string $filename, string $tmpFilename): void
    {
        $fileHandle = fopen($tmpFilename, 'rb');
        $this->filesystem->updateStream($filename, $fileHandle);
        fclose($fileHandle);
    }
}
