<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class ContextController extends StorefrontController
{
    /**
     * @var SalesChannelContextSwitcher
     */
    private $contextSwitcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $domainRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        SalesChannelContextSwitcher $contextSwitcher,
        EntityRepositoryInterface $domainRepository,
        RequestStack $requestStack,
        RouterInterface $router
    ) {
        $this->contextSwitcher = $contextSwitcher;
        $this->domainRepository = $domainRepository;
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    /**
     * @Route("/checkout/configure", name="frontend.checkout.configure", methods={"POST"}, options={"seo"="false"}, defaults={"XmlHttpRequest": true})
     */
    public function configure(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $route = $request->get('redirectTo', 'frontend.checkout.cart.page');
        $parameters = $request->get('redirectParameters', []);

        //since the keys "redirectTo" and "redirectParameters" are used to configure this action, the shall not be persisted
        $data->remove('redirectTo');
        $data->remove('redirectParameters');

        $this->contextSwitcher->update($data, $context);

        return $this->redirectToRoute($route, $parameters);
    }

    /**
     * @Route("/checkout/language", name="frontend.checkout.switch-language", methods={"POST"})
     */
    public function switchLanguage(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if (!$request->request->has('languageId')) {
            throw new MissingRequestParameterException('languageId');
        }

        $languageId = $request->request->get('languageId');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('languageId', $languageId));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->setLimit(1);

        $domain = $this->domainRepository->search($criteria, $context->getContext())->first();

        /** @var SalesChannelDomainEntity $domain */
        if (!$domain) {
            throw new LanguageNotFoundException($languageId);
        }

        $route = $request->request->get('redirectTo', 'frontend.home.page');

        $params = $request->request->get('redirectParameters', json_encode([]));

        if (is_string($params)) {
            $params = json_decode($params, true);
        }

        $mappingRequest = new Request([], [], [], [], [], ['REQUEST_URI' => $domain->getUrl()]);

        $this->requestStack->getMasterRequest()->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, $mappingRequest->getPathInfo());

        $this->router->getContext()->setMethod('GET');
        $url = $this->router->generate($route, $params, Router::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }
}
