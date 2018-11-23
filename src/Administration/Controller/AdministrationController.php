<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Search\AdministrationSearch;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdministrationController extends AbstractController
{
    /**
     * @var AdministrationSearch
     */
    private $search;

    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(AdministrationSearch $search, TemplateFinder $finder)
    {
        $this->search = $search;
        $this->finder = $finder;
    }

    /**
     * @Route("/admin", name="administration")
     */
    public function index(): Response
    {
        $template = $this->finder->find('administration/index.html.twig', true);

        return $this->render($template, ['features' => FeatureConfig::getAll()]);
    }

    /**
     * @Route("/api/v{version}/admin/search", name="administration.search")
     */
    public function search(Request $request, Context $context): JsonResponse
    {
        $term = $request->query->get('term');
        $limit = $request->query->getInt('limit', 20);

        $result = $this->search->search($term, $limit, $context, $context->getSourceContext()->getUserId());

        $result = json_decode(json_encode($result), true);

        return new JsonResponse(JsonType::format($result));
    }
}
