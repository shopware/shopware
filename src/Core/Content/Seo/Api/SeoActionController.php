<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Api;

use Shopware\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopware\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\Validation\SeoUrlDataValidationFactoryInterface;
use Shopware\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SeoActionController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
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
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    #[Route(
        path: '/api/_action/seo-url-template/validate',
        name: 'api.seo-url-template.validate',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:update']],
        methods: [Request::METHOD_POST]
    )]
    public function validate(Request $request, Context $context): JsonResponse
    {
        $context->setConsiderInheritance(true);

        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        // just call it to validate the template
        $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_action/seo-url-template/preview',
        name: 'api.seo-url-template.preview',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:update']],
        methods: [Request::METHOD_POST]
    )]
    public function preview(Request $request, Context $context): Response
    {
        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        $previewCriteria = new Criteria();
        if (\array_key_exists('criteria', $seoUrlTemplate) && \is_string($seoUrlTemplate['entityName']) && \is_array($seoUrlTemplate['criteria'])) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($seoUrlTemplate['entityName']);

            $previewCriteria = $this->requestCriteriaBuilder->handleRequest(
                Request::create('', Request::METHOD_POST, $seoUrlTemplate['criteria']),
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

    #[Route(
        path: '/api/_action/seo-url-template/context',
        name: 'api.seo-url-template.context',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:read']],
        methods: [Request::METHOD_POST]
    )]
    public function getSeoUrlContext(RequestDataBag $data, Context $context): JsonResponse
    {
        $routeName = $data->get('routeName');
        $fk = $data->get('foreignKey');
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if ($seoUrlRoute === null) {
            throw SeoException::seoUrlRouteNotFound((string) $routeName);
        }

        // Headless store-api routes only implement EntitySeoUrlRouteInterface and do not provide the mapping
        // themselves; wrapping the resolved route in a ConfiguredSeoUrlRoute exposes a generic mapping (entity
        // by name) while delegating to the real mapping for registered storefront routes.
        $route = new ConfiguredEntitySeoUrlRoute($seoUrlRoute);

        $entity = $this->loadPreviewEntity($route->getConfig(), $fk, $context);
        if ($entity === null) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $mapping = $route->getMapping($entity, null);

        return new JsonResponse($mapping->getSeoPathInfoContext());
    }

    #[Route(
        path: '/api/_action/seo-url/canonical',
        name: 'api.seo-url.canonical',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateCanonicalUrl(RequestDataBag $seoUrl, Context $context): Response
    {
        if (!$seoUrl->has('routeName')) {
            throw SeoException::routeNameParameterIsMissing();
        }

        $routeName = $seoUrl->get('routeName') ?? '';
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);
        if (!$seoUrlRoute) {
            throw SeoException::seoUrlRouteNotFound($seoUrl->get('routeName'));
        }

        $validation = $this->seoUrlValidator->buildValidation($context, $seoUrlRoute->getConfig());

        $seoUrlData = $seoUrl->all();
        $this->validator->validate($seoUrlData, $validation);
        $seoUrlData['isModified'] ??= true;

        $salesChannelId = $seoUrlData['salesChannelId'] ?? null;

        if ($salesChannelId === null) {
            throw SeoException::salesChannelIdParameterIsMissing();
        }

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->getEntities()->first();

        if ($salesChannel === null) {
            throw SeoException::salesChannelNotFound($salesChannelId);
        }

        // when updating a canonical url for a headless sales channel with the route of the storefront
        // the route name should be corrected to use the equivalent store-api route using the entity name to match
        $seoUrlData = [
            ...$seoUrlData,
            ...$this->entityRouteResolver->getSeoUrlRouteNameAndPathInfo(
                $seoUrlRoute->getConfig()->getDefinition()->getEntityName(),
                $seoUrlData['routeName'],
                $seoUrlData['foreignKey'],
                $salesChannel->getTypeId()
            ),
        ];

        $this->seoUrlPersister->forceUpdateSeoUrls(
            $context,
            $seoUrlData['routeName'],
            [$seoUrlData['foreignKey']],
            [$seoUrlData],
            $salesChannel,
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/seo-url/create-custom-url',
        name: 'api.seo-url.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url:create']],
        methods: [Request::METHOD_POST]
    )]
    public function createCustomSeoUrls(RequestDataBag $dataBag, Context $context): Response
    {
        /** @var ParameterBag $dataBag */
        $dataBag = $dataBag->get('urls');
        $urls = $dataBag->all();

        /** @var SeoUrlValidationFactory $validatorBuilder */
        $validatorBuilder = $this->seoUrlValidator;

        $validation = $validatorBuilder->buildValidation($context, null);
        $salesChannels = new SalesChannelCollection();

        $salesChannelIds = array_column($urls, 'salesChannelId');

        if ($salesChannelIds !== []) {
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
            if ($salesChannelId === '') {
                throw SeoException::salesChannelIdParameterIsMissing();
            }

            $salesChannelEntity = $salesChannels->get($salesChannelId);

            if ($salesChannelEntity === null) {
                throw SeoException::salesChannelNotFound((string) $salesChannelId);
            }

            $this->seoUrlPersister->forceUpdateSeoUrls(
                $context,
                $writeRows[0]['routeName'],
                array_column($writeRows, 'foreignKey'),
                $writeRows,
                $salesChannelEntity,
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/seo-url-template/default/{routeName}',
        name: 'api.seo-url-template.default',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getDefaultSeoTemplate(string $routeName, Context $context): JsonResponse
    {
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if (!$seoUrlRoute) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        return new JsonResponse(['defaultTemplate' => $seoUrlRoute->getConfig()->getTemplate()]);
    }

    private function getEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        return $this->seoUrlRouteRegistry->findByRouteName($routeName)
            ?? $this->entityRouteResolver->findEntitySeoUrlRoute($routeName);
    }

    private function validateSeoUrlTemplate(Request $request): void
    {
        if (!$request->request->has('template')) {
            throw SeoException::templateParameterIsMissing();
        }

        if (!$request->request->has('salesChannelId')) {
            throw SeoException::salesChannelIdParameterIsMissing();
        }

        if (!$request->request->has('routeName')) {
            throw SeoException::routeNameParameterIsMissing();
        }

        if (!$request->request->has('entityName')) {
            throw SeoException::entityNameParameterIsMissing();
        }
    }

    /**
     * @param array<string, mixed> $seoUrlTemplate
     *
     * @return array<SeoUrlEntity>
     */
    private function getPreview(array $seoUrlTemplate, Context $context, ?Criteria $previewCriteria = null): array
    {
        $routeName = $seoUrlTemplate['routeName'];

        // Registered storefront routes resolve directly; store-api routes (headless) resolve via the tagged
        // entity routes. Either way they are wrapped in ConfiguredSeoUrlRoute so the generator can render them.
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if ($seoUrlRoute === null) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        $route = new ConfiguredEntitySeoUrlRoute($seoUrlRoute);
        $route->getConfig()->setSkipInvalid(false);

        $repository = $this->getRepository($route->getConfig());

        $salesChannel = $this->resolveSalesChannel($seoUrlTemplate, $context);
        if ($salesChannel === null) {
            throw SeoException::salesChannelIdParameterIsMissing();
        }

        $template = $seoUrlTemplate['template'] ?? '';

        $criteria = $previewCriteria ?? new Criteria();
        $criteria->setLimit(10);
        $route->prepareCriteria($criteria, $salesChannel);

        $ids = $repository->searchIds($criteria, $context)->getIds();
        if ($ids === []) {
            throw SeoException::noEntitiesForPreview($repository->getDefinition()->getEntityName(), $routeName);
        }

        $result = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        if ($salesChannel->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_API) {
            return $result;
        }

        $externalStorefrontDomain = $this->getExternalStorefrontDomain($salesChannel, $context);
        if ($externalStorefrontDomain === null) {
            return $result;
        }

        foreach ($result as $seoUrl) {
            $seoUrl->setSeoPathInfo(rtrim($externalStorefrontDomain, '/') . '/' . ltrim($seoUrl->getSeoPathInfo(), '/'));
        }

        return $result;
    }

    private function getExternalStorefrontDomain(SalesChannelEntity $salesChannel, Context $context): ?string
    {
        return $salesChannel->getDomains()
            ?->firstWhere(static fn (SalesChannelDomainEntity $domain): bool => $domain->getIsExternalStorefront()
                && $domain->getLanguageId() === $context->getLanguageId())
            ?->getUrl();
    }

    private function loadPreviewEntity(SeoUrlRouteConfig $config, ?string $foreignKey, Context $context): ?Entity
    {
        $criteria = $foreignKey !== null && $foreignKey !== '' ? new Criteria([$foreignKey]) : new Criteria();
        $criteria->setLimit(1);

        return $this->getRepository($config)
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    /**
     * @param array<string, mixed> $seoUrlTemplate
     */
    private function resolveSalesChannel(array $seoUrlTemplate, Context $context): ?SalesChannelEntity
    {
        if (isset($seoUrlTemplate['salesChannelId']) && \is_string($seoUrlTemplate['salesChannelId'])) {
            $criteria = (new Criteria([$seoUrlTemplate['salesChannelId']]))->setLimit(1);
        } else {
            $criteria = (new Criteria())
                ->addFilter(new OrFilter([
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API),
                ]))
                ->setLimit(1);
        }
        $criteria->addAssociation('domains');
        $criteria->addSorting(new FieldSorting('typeId'));

        return $this->salesChannelRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    private function getRepository(SeoUrlRouteConfig $config): EntityRepository
    {
        return $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());
    }
}
