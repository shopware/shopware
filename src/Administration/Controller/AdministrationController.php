<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Administration\Search\AuditLogSearch;
use Shopware\Rest\ApiContext;
use Shopware\Storefront\Twig\TemplateFinder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route(service="shopware.administration.controller", path="/")
 */
class AdministrationController extends Controller
{
    /**
     * @var AuditLogSearch
     */
    private $search;

    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(AuditLogSearch $search, TemplateFinder $finder)
    {
        $this->search = $search;
        $this->finder = $finder;
    }

    /**
     * @Route("/admin", name="administration")
     */
    public function indexAction()
    {
        $template = $this->finder->find('administration/index.html.twig', true);

        return $this->render($template);
    }

    /**
     * @Route("/api/admin/search", name="administration.search")
     *
     * @param Request $request
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return JsonResponse
     */
    public function searchAction(Request $request, ApiContext $apiContext): JsonResponse
    {
        $term = $request->query->getAlpha('term');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $context = $apiContext->getTranslationContext();
        $result = $this->search->search($term, $page, $limit, $context, $apiContext->getUserId());

        return new JsonResponse($result);
    }
}
