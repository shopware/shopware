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

namespace Shopware\Storefront\Twig;

use Doctrine\DBAL\Connection;
use Shopware\Category\CategoryRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Serializer\SerializerRegistry;
use Shopware\Storefront\Component\SitePageMenu;
use Shopware\Storefront\Theme\ThemeConfigReader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

class TemplateDataExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var SitePageMenu
     */
    private $sitePageMenu;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ThemeConfigReader
     */
    private $themeConfigReader;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var SerializerRegistry
     */
    private $serializer;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        ConfigServiceInterface $configService,
        SitePageMenu $sitePageMenu,
        Connection $connection,
        ThemeConfigReader $themeConfigReader,
        CategoryRepository $categoryRepository,
        SerializerRegistry $serializer
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->configService = $configService;
        $this->sitePageMenu = $sitePageMenu;
        $this->connection = $connection;
        $this->themeConfigReader = $themeConfigReader;
        $this->categoryRepository = $categoryRepository;
        $this->serializer = $serializer;
    }

    public function getFunctions(): array
    {
        return [
            new \Twig_Function('snippet', function ($snippet, $namespace = null) {
                return $this->translator->trans($snippet, [], $namespace);
            }),
        ];
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [];
        }

        /** @var ShopContext $context */
        $context = $request->attributes->get('_shop_context');
        if (!$context) {
            return [];
        }

        return [
            'shopware' => [
                'config' => $this->configService->getByShop(
                    $context->getShop()->getUuid(),
                    $context->getShop()->getParentUuid()
                ),
                'theme' => $this->getThemeConfig(),
            ],
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
        ];
    }

    /**
     * @return array
     */
    protected function getThemeConfig(): array
    {
        $themeConfig = $this->themeConfigReader->get();

        $themeConfig = array_merge(
            $themeConfig,
            [
                'desktopLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'tabletLandscapeLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'tabletLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'mobileLogo' => 'bundles/storefront/src/img/logos/logo--mobile.png',
                'favicon' => 'bundles/storefront/src/img/favicon.ico',
            ]
        );

        return $themeConfig;
    }
}
