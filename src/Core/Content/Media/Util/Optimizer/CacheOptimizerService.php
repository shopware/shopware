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

namespace Shopware\Content\Media\Util\Optimizer;

class CacheOptimizerService implements OptimizerServiceInterface
{
    /**
     * Indexed by mime-type
     *
     * @var OptimizerInterface[]
     */
    private $mimeCache = [];

    /**
     * @var OptimizerServiceInterface
     */
    private $optimizerService;

    /**
     * @param OptimizerServiceInterface $optimizerService
     */
    public function __construct(OptimizerServiceInterface $optimizerService)
    {
        $this->optimizerService = $optimizerService;
    }

    /**
     * {@inheritdoc}
     */
    public function optimize(string $filename): void
    {
        $this->optimizerService->optimize($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptimizers(): array
    {
        return $this->optimizerService->getOptimizers();
    }

    /**
     * {@inheritdoc}
     */
    public function getOptimizerByMimeType(string $mimeType): OptimizerInterface
    {
        if (array_key_exists($mimeType, $this->mimeCache)) {
            return $this->mimeCache[$mimeType];
        }

        return $this->mimeCache[$mimeType] = $this->optimizerService->getOptimizerByMimeType($mimeType);
    }
}
