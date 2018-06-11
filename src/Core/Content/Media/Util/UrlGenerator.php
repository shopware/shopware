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

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $baseUrl = null;

    /**
     * @var StrategyInterface
     */
    private $strategy;

    public function __construct(StrategyInterface $strategy, RequestStack $requestStack, string $baseUrl = null)
    {
        $this->strategy = $strategy;
        $this->requestStack = $requestStack;
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(string $filename): string
    {
        if (!$this->baseUrl) {
            $this->baseUrl = $this->createFallbackMediaUrl();
        }

        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        $filename = $this->strategy->encode($filename);

        return $this->baseUrl . '/' . $filename;
    }

    private function normalizeBaseUrl(string $mediaUrl = null): ?string
    {
        if (!$mediaUrl) {
            return null;
        }

        return rtrim($mediaUrl, '/');
    }

    /**
     * Generates a mediaUrl based on the request or router
     *
     * @throws \Exception
     *
     * @return string
     */
    private function createFallbackMediaUrl(): ?string
    {
        $request = $this->requestStack->getMasterRequest();
        if ($request) {
            return $this->normalizeBaseUrl(
                $request->getSchemeAndHttpHost() . $request->getBasePath() . '/media'
            );
        }

        //todo@next: resolve default shop path
        return $this->normalizeBaseUrl('');
    }
}
