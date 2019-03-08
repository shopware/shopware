<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Seo\Exception\InvalidTemplateException;
use Shopware\Storefront\Framework\Seo\SeoServiceInterface;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
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
        $data = $request->request->all();
        $seoUrlTemplate = $this->hydrateSeoUrlTemplate($data);

        // just call it to validate
        $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/seo-url-template/preview", name="api.seo-url-template.preview", methods={"POST"}, requirements={"version"="\d+"})
     */
    public function preview(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $seoUrlTemplate = $this->hydrateSeoUrlTemplate($data);
        $preview = $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse($preview);
    }

    private function hydrateSeoUrlTemplate(array $data): SeoUrlTemplateEntity
    {
        if (!isset($data['template']) || !$data['template']) {
            throw new InvalidTemplateException('Empty template');
        }

        $seoUrlTemplate = new SeoUrlTemplateEntity();
        $seoUrlTemplate->assign($data);

        return $seoUrlTemplate;
    }

    private function getPreview(SeoUrlTemplateEntity $seoUrlTemplate, Context $context): iterable
    {
        $repo = $this->definitionRegistry->getRepository($seoUrlTemplate->getEntityName());

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $ids = $repo->searchIds($criteria, $context)->getIds();

        return $this->seoService->generateSeoUrls(
            $seoUrlTemplate->getSalesChannelId(),
            $seoUrlTemplate->getRouteName(),
            $ids,
            $seoUrlTemplate->getTemplate()
        );
    }
}
