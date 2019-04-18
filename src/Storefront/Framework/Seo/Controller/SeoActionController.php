<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Framework\Seo\Exception\InvalidTemplateException;
use Shopware\Storefront\Framework\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SeoActionController extends AbstractController
{
    /**
     * @var SeoService
     */
    private $seoService;

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    public function __construct(SeoService $seoService, DefinitionRegistry $definitionRegistry)
    {
        $this->seoService = $seoService;
        $this->definitionRegistry = $definitionRegistry;
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
        $entityName = $data->get('entityName');
        $fk = $data->get('foreignKey');

        $repo = $this->definitionRegistry->getRepository($entityName);

        /** @var Entity|null $entity */
        $entity = $repo->search((new Criteria($fk ? [$fk] : []))->setLimit(1), $context)->first();
        if (!$entity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $context = $this->seoService->getSeoUrlContext($routeName, $entity);

        return new JsonResponse($context);
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
        $repo = $this->definitionRegistry->getRepository($seoUrlTemplate['entityName']);

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $ids = $repo->searchIds($criteria, $context)->getIds();

        return iterator_to_array($this->seoService->generateSeoUrls(
            $seoUrlTemplate['salesChannelId'] ?? null,
            $seoUrlTemplate['routeName'],
            $ids,
            $seoUrlTemplate['template'],
            false
        ));
    }
}
