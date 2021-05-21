<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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

/**
 * @RouteScope(scopes={"storefront"})
 */
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

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        SalesChannelContextSwitcher $contextSwitcher,
        EntityRepositoryInterface $domainRepository,
        EntityRepositoryInterface $customerRepository,
        RequestStack $requestStack,
        RouterInterface $router
    ) {
        $this->contextSwitcher = $contextSwitcher;
        $this->domainRepository = $domainRepository;
        $this->customerRepository = $customerRepository;
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/configure", name="frontend.checkout.configure", methods={"POST"}, options={"seo"="false"}, defaults={"XmlHttpRequest": true})
     */
    public function configure(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->contextSwitcher->update($data, $context);

        return $this->createActionResponse($request);
    }

    /**
     * @Since("6.0.0.0")
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

        /** @var SalesChannelDomainEntity|null $domain */
        $domain = $this->domainRepository->search($criteria, $context->getContext())->first();
        if ($domain === null) {
            throw new LanguageNotFoundException($languageId);
        }

        if ($context->getCustomer()) {
            $this->customerRepository->update([
                [
                    'id' => $context->getCustomer()->getId(),
                    'languageId' => $languageId,
                ],
            ], $context->getContext());
        }

        $route = (string) $request->request->get('redirectTo', 'frontend.home.page');

        $params = $request->request->get('redirectParameters', json_encode([]));

        if (\is_string($params)) {
            $params = json_decode($params, true);
        }

        /*
         * possible domains
         *
         * http://shopware.de/de
         * http://shopware.de/en
         * http://shopware.de/fr
         *
         * http://shopware.fr
         * http://shopware.com
         * http://shopware.de
         *
         * http://color.com
         * http://farben.de
         * http://couleurs.fr
         *
         * http://localhost/development/public/de
         * http://localhost/development/public/en
         * http://localhost/development/public/fr
         * http://localhost/development/public/de-DE
         *
         * http://localhost:8080
         * http://localhost:8080/en
         * http://localhost:8080/fr
         * http://localhost:8080/de-DE
         */
        $parsedUrl = parse_url($domain->getUrl());

        $routerContext = $this->router->getContext();
        $routerContext->setHttpPort($parsedUrl['port'] ?? 80);
        $routerContext->setMethod('GET');
        $routerContext->setHost($parsedUrl['host']);
        $routerContext->setBaseUrl(rtrim($parsedUrl['path'] ?? '', '/'));

        $this->requestStack->getMainRequest()
            ->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, '');

        $url = $this->router->generate($route, $params, Router::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }
}
