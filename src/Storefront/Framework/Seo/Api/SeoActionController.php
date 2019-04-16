<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Framework\Seo\Exception\InvalidTemplateException;
use Shopware\Storefront\Framework\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Storefront\Framework\Seo\SeoUrlGenerator;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\TemplateGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SeoActionController extends AbstractController
{
    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlGenerator;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;
    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    public function __construct(SeoUrlGenerator $seoUrlGenerator, DefinitionInstanceRegistry $definitionRegistry, SeoUrlRouteRegistry $seoUrlRouteRegistry)
    {
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->definitionRegistry = $definitionRegistry;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/seo-url-template/validate", name="api.seo-url-template.validate", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function validate(Request $request, Context $context): JsonResponse
    {
        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        // just call it to validate the template
        $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/seo-url-template/preview", name="api.seo-url-template.preview", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function preview(Request $request, Context $context): JsonResponse
    {
        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();
        $preview = $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse($preview);
    }

    /**
     * @Route("/api/v{version}/_action/seo-url-template/context", name="api.seo-url-template.context", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function getSeoUrlContext(RequestDataBag $data, Context $context): JsonResponse
    {
        $routeName = $data->get('routeName');
        $fk = $data->get('foreignKey');
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($routeName);
        if (!$seoUrlRoute) {
            throw new SeoUrlRouteNotFoundException($routeName);
        }

        $config = $seoUrlRoute->getConfig();
        $repository = $this->getRepository($config);

        /** @var Entity|null $entity */
        $entity = $repository
            ->search((new Criteria($fk ? [$fk] : []))->setLimit(1), $context)
            ->first();

        if (!$entity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $mapping = $seoUrlRoute->getMapping($entity);

        return new JsonResponse($mapping->getSeoPathInfoContext());
    }

    private function validateSeoUrlTemplate(Request $request): void
    {
        $keys = ['template', 'salesChannelId', 'routeName', 'entityName'];
        foreach ($keys as $key) {
            if (!$request->request->has($key)) {
                throw new InvalidTemplateException($key . ' is required');
            }
        }
    }

    private function getPreview(array $seoUrlTemplate, Context $context): array
    {
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($seoUrlTemplate['routeName']);

        if (!$seoUrlRoute) {
            throw new SeoUrlRouteNotFoundException($seoUrlTemplate['routeName']);
        }

        $config = $seoUrlRoute->getConfig();
        $config->setSkipInvalid(false);
        $repository = $this->getRepository($config);

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $ids = $repository->searchIds($criteria, $context)->getIds();

        $templateString = $seoUrlTemplate['template'];
        $groups = [new TemplateGroup($context->getLanguageId(), $templateString, [$seoUrlTemplate['salesChannelId'] ?? null])];
        $result = $this->seoUrlGenerator->generateSeoUrls($context, $seoUrlRoute, $ids, $groups, $config);

        return iterator_to_array($result);
    }

    private function getRepository(SeoUrlRouteConfig $config): EntityRepositoryInterface
    {
        return $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());
    }
}
