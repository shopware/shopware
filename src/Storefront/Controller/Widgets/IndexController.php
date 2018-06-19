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

namespace Shopware\Storefront\Controller\Widgets;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Storefront\Controller\StorefrontController;

class IndexController extends StorefrontController
{
    /**
     * @var RepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    public function __construct(RepositoryInterface $currencyRepository, RepositoryInterface $languageRepository)
    {
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
    }

    /**
     * @Route("/widgets/index/shopMenu", name="widgets/shopMenu")
     * @Method({"GET"})
     *
     * @param CheckoutContext $context
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shopMenuAction(CheckoutContext $context)
    {
        $languages = $this->loadLanguages($context);

        return $this->render('@Storefront/widgets/index/shop_menu.html.twig', [
            'application' => $context->getTouchpoint(),
            'currency' => $context->getCurrency(),
            'languages' => $languages,
            'language' => $languages->get($context->getContext()->getLanguageId()),
            'currencies' => $this->getCurrencies($context),
        ]);
    }

    private function loadLanguages(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('language.id', $context->getTouchpoint()->getLanguageIds()));

        return $this->languageRepository->search($criteria, $context->getContext());
    }

    private function getCurrencies(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('currency.id', ['4c8eba11-bd35-46d7-86af-bed481a6e665', '2824ea63-db67-4110-9e23-78ddcc9cec84']));

        return $this->currencyRepository->search($criteria, $context->getContext());
    }
}
