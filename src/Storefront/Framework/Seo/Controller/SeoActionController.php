<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Seo\Exception\InvalidTemplateException;
use Shopware\Storefront\Framework\Seo\SeoServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SeoActionController extends AbstractController
{
    /**
     * @var SeoServiceInterface
     */
    private $seoService;

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    public function __construct(SeoServiceInterface $seoService, DefinitionRegistry $definitionRegistry)
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

    private function validateSeoUrlTemplate(Request $request): void
    {
        $keys = ['template', 'salesChannelId', 'routeName', 'entityName'];
        foreach ($keys as $key) {
            if (!$request->request->has($key)) {
                throw new InvalidTemplateException($key . ' is required');
            }
        }
    }

    private function getPreview(array $seoUrlTemplate, Context $context): iterable
    {
        $repo = $this->definitionRegistry->getRepository($seoUrlTemplate['entityName']);

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $ids = $repo->searchIds($criteria, $context)->getIds();

        return $this->seoService->generateSeoUrls(
            $seoUrlTemplate['salesChannelId'],
            $seoUrlTemplate['routeName'],
            $ids,
            $seoUrlTemplate['template']
        );
    }
}
