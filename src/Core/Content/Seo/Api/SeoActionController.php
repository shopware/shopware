<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Api;

use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\Validation\SeoUrlDataValidationFactoryInterface;
use Shopware\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('sales-channel')]
class SeoActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly SeoUrlDataValidationFactoryInterface $seoUrlValidator,
        private readonly DataValidator $validator,
        private readonly EntityRepository $salesChannelRepository,
        private readonly RequestCriteriaBuilder $requestCriteriaBuilder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
    }

    #[Route(path: '/api/_action/seo-url-template/validate', name: 'api.seo-url-template.validate', methods: ['POST'])]
    public function validate(Request $request, Context $context): JsonResponse
    {
        $context->setConsiderInheritance(true);

        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        // just call it to validate the template
        $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/seo-url-template/preview', name: 'api.seo-url-template.preview', methods: ['POST'])]
    public function preview(Request $request, Context $context): Response
    {
        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        $previewCriteria = new Criteria();
        if (\array_key_exists('criteria', $seoUrlTemplate) && \is_string($seoUrlTemplate['entityName']) && \is_array($seoUrlTemplate['criteria'])) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($seoUrlTemplate['entityName']);

            $previewCriteria = $this->requestCriteriaBuilder->handleRequest(
                Request::create('', 'POST', $seoUrlTemplate['criteria']),
                $previewCriteria,
                $definition,
                $context
            );
            unset($seoUrlTemplate['criteria']);
        }

        try {
            $preview = $this->getPreview($seoUrlTemplate, $context, $previewCriteria);
        } catch (NoEntitiesForPreviewException) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($preview);
    }

    #[Route(path: '/api/_action/seo-url-template/context', name: 'api.seo-url-template.context', methods: ['POST'])]
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

        $criteria = new Criteria();
        if (!empty($fk)) {
            $criteria = new Criteria([$fk]);
        }
        $criteria->setLimit(1);

        $entity = $repository
            ->search($criteria, $context)
            ->first();

        if (!$entity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $mapping = $seoUrlRoute->getMapping($entity, null);

        return new JsonResponse($mapping->getSeoPathInfoContext());
    }

    #[Route(path: '/api/_action/seo-url/canonical', name: 'api.seo-url.canonical', methods: ['PATCH'])]
    public function updateCanonicalUrl(RequestDataBag $seoUrl, Context $context): Response
    {
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($seoUrl->get('routeName') ?? '');
        if (!$seoUrlRoute) {
            throw new SeoUrlRouteNotFoundException($seoUrl->get('routeName') ?? '');
        }

        $validation = $this->seoUrlValidator->buildValidation($context, $seoUrlRoute->getConfig());

        $seoUrlData = $seoUrl->all();
        $this->validator->validate($seoUrlData, $validation);
        $seoUrlData['isModified'] ??= true;

        $salesChannelId = $seoUrlData['salesChannelId'] ?? null;

        if ($salesChannelId === null) {
            throw RoutingException::missingRequestParameter('salesChannelId');
        }

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->first();

        if ($salesChannel === null) {
            throw RoutingException::invalidRequestParameter('salesChannelId');
        }

        $this->seoUrlPersister->updateSeoUrls(
            $context,
            $seoUrlData['routeName'],
            [$seoUrlData['foreignKey']],
            [$seoUrlData],
            $salesChannel
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/seo-url/create-custom-url', name: 'api.seo-url.create', methods: ['POST'])]
    public function createCustomSeoUrls(RequestDataBag $dataBag, Context $context): Response
    {
        $urls = $dataBag->get('urls')->all();

        /** @var SeoUrlValidationFactory $validatorBuilder */
        $validatorBuilder = $this->seoUrlValidator;

        $validation = $validatorBuilder->buildValidation($context, null);
        $salesChannels = new SalesChannelCollection();

        $salesChannelIds = array_column($urls, 'salesChannelId');

        if (!empty($salesChannelIds)) {
            $salesChannels = $this->salesChannelRepository->search(new Criteria($salesChannelIds), $context)->getEntities();
        }

        $writeData = [];

        foreach ($urls as $seoUrlData) {
            $id = $seoUrlData['salesChannelId'] ?? null;

            $this->validator->validate($seoUrlData, $validation);
            $seoUrlData['isModified'] ??= true;

            $writeData[$id][] = $seoUrlData;
        }

        foreach ($writeData as $salesChannelId => $writeRows) {
            $salesChannelEntity = null;

            if ($salesChannelId === '') {
                throw RoutingException::invalidRequestParameter('salesChannelId');
            }

            /** @var SalesChannelEntity $salesChannelEntity */
            $salesChannelEntity = $salesChannels->get($salesChannelId);

            $this->seoUrlPersister->updateSeoUrls(
                $context,
                $writeRows[0]['routeName'],
                array_column($writeRows, 'foreignKey'),
                $writeRows,
                $salesChannelEntity
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/seo-url-template/default/{routeName}', name: 'api.seo-url-template.default', methods: ['GET'])]
    public function getDefaultSeoTemplate(string $routeName, Context $context): JsonResponse
    {
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($routeName);

        if (!$seoUrlRoute) {
            throw new SeoUrlRouteNotFoundException($routeName);
        }

        return new JsonResponse(['defaultTemplate' => $seoUrlRoute->getConfig()->getTemplate()]);
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

    /**
     * @param array<string, mixed> $seoUrlTemplate
     *
     * @return list<SeoUrlEntity>
     */
    private function getPreview(array $seoUrlTemplate, Context $context, ?Criteria $previewCriteria = null): array
    {
        $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($seoUrlTemplate['routeName']);

        if (!$seoUrlRoute) {
            throw new SeoUrlRouteNotFoundException($seoUrlTemplate['routeName']);
        }

        $config = $seoUrlRoute->getConfig();
        $config->setSkipInvalid(false);
        $repository = $this->getRepository($config);

        $criteria = new Criteria();
        if ($previewCriteria !== null) {
            $criteria = $previewCriteria;
        }
        $criteria->setLimit(10);

        $ids = $repository->searchIds($criteria, $context)->getIds();

        if (empty($ids)) {
            throw new NoEntitiesForPreviewException($repository->getDefinition()->getEntityName(), $seoUrlTemplate['routeName']);
        }

        $salesChannelId = $seoUrlTemplate['salesChannelId'] ?? null;
        $template = $seoUrlTemplate['template'] ?? '';

        if ($salesChannelId) {
            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->salesChannelRepository->search((new Criteria([$salesChannelId]))->setLimit(1), $context)->get($salesChannelId);

            if ($salesChannel === null) {
                throw new InvalidSalesChannelIdException((string) $salesChannelId);
            }
        } else {
            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->salesChannelRepository
                ->search(
                    (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))->setLimit(1),
                    $context
                )
                ->first();
        }

        if ($salesChannel === null) {
            throw RoutingException::invalidRequestParameter('salesChannelId');
        }

        $result = $this->seoUrlGenerator->generate($ids, $template, $seoUrlRoute, $context, $salesChannel);
        if (\is_array($result)) {
            return $result;
        }

        return iterator_to_array($result);
    }

    private function getRepository(SeoUrlRouteConfig $config): EntityRepository
    {
        return $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());
    }
}
