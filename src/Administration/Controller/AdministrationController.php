<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Events\PreResetExcludedSearchTermEvent;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function version_compare;

#[Route(defaults: ['_routeScope' => ['administration']])]
#[Package('administration')]
class AdministrationController extends AbstractController
{
    private readonly bool $esAdministrationEnabled;

    private readonly bool $esStorefrontEnabled;

    /**
     * @internal
     *
     * @param array<int, int> $supportedApiVersions
     */
    public function __construct(
        private readonly TemplateFinder $finder,
        private readonly FirstRunWizardService $firstRunWizardService,
        private readonly SnippetFinderInterface $snippetFinder,
        private readonly array $supportedApiVersions,
        private readonly KnownIpsCollectorInterface $knownIpsCollector,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $shopwareCoreDir,
        private readonly EntityRepository $customerRepo,
        private readonly EntityRepository $currencyRepository,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        ParameterBagInterface $params,
        private readonly SystemConfigService $systemConfigService
    ) {
        // param is only available if the elasticsearch bundle is enabled
        $this->esAdministrationEnabled = $params->has('elasticsearch.administration.enabled')
            ? $params->get('elasticsearch.administration.enabled')
            : false;
        $this->esStorefrontEnabled = $params->has('elasticsearch.enabled')
            ? $params->get('elasticsearch.enabled')
            : false;
    }

    #[Route(path: '/%shopware_administration.path_name%', name: 'administration.index', defaults: ['auth_required' => false], methods: ['GET'])]
    public function index(Request $request, Context $context): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');

        /** @var CurrencyEntity $defaultCurrency */
        $defaultCurrency = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), $context)->first();

        return $this->render($template, [
            'features' => Feature::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => Defaults::CURRENCY,
            'disableExtensions' => EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false),
            'systemCurrencyISOCode' => $defaultCurrency->getIsoCode(),
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardService->frwShouldRun(),
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE),
            'adminEsEnable' => $this->esAdministrationEnabled,
            'storefrontEsEnable' => $this->esStorefrontEnabled,
        ]);
    }

    #[Route(path: '/api/_admin/snippets', name: 'api.admin.snippets', methods: ['GET'])]
    public function snippets(Request $request): Response
    {
        $snippets = [];
        $locale = $request->query->get('locale', 'en-GB');
        $snippets[$locale] = $this->snippetFinder->findSnippets((string) $locale);

        if ($locale !== 'en-GB') {
            $snippets['en-GB'] = $this->snippetFinder->findSnippets('en-GB');
        }

        return new JsonResponse($snippets);
    }

    #[Route(path: '/api/_admin/known-ips', name: 'api.admin.known-ips', methods: ['GET'])]
    public function knownIps(Request $request): Response
    {
        $ips = [];

        foreach ($this->knownIpsCollector->collectIps($request) as $ip => $name) {
            $ips[] = [
                'name' => $name,
                'value' => $ip,
            ];
        }

        return new JsonResponse(['ips' => $ips]);
    }

    #[Route(path: '/api/_admin/reset-excluded-search-term', name: 'api.admin.reset-excluded-search-term', defaults: ['_acl' => ['system_config:update', 'system_config:create', 'system_config:delete']], methods: ['POST'])]
    public function resetExcludedSearchTerm(Context $context): JsonResponse
    {
        $searchConfigId = $this->connection->fetchOne('SELECT id FROM product_search_config WHERE language_id = :language_id', ['language_id' => Uuid::fromHexToBytes($context->getLanguageId())]);

        if ($searchConfigId === false) {
            throw new LanguageNotFoundException($context->getLanguageId());
        }

        $deLanguageId = $this->fetchLanguageIdByName('de-DE', $this->connection);
        $enLanguageId = $this->fetchLanguageIdByName('en-GB', $this->connection);

        switch ($context->getLanguageId()) {
            case $deLanguageId:
                $defaultExcludedTerm = require $this->shopwareCoreDir . '/Migration/Fixtures/stopwords/de.php';

                break;
            case $enLanguageId:
                $defaultExcludedTerm = require $this->shopwareCoreDir . '/Migration/Fixtures/stopwords/en.php';

                break;
            default:
                /** @var PreResetExcludedSearchTermEvent $preResetExcludedSearchTermEvent */
                $preResetExcludedSearchTermEvent = $this->eventDispatcher->dispatch(new PreResetExcludedSearchTermEvent($searchConfigId, [], $context));
                $defaultExcludedTerm = $preResetExcludedSearchTermEvent->getExcludedTerms();
        }

        $this->connection->executeStatement(
            'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
            [
                'excludedTerms' => json_encode($defaultExcludedTerm, \JSON_THROW_ON_ERROR),
                'id' => $searchConfigId,
            ]
        );

        return new JsonResponse([
            'success' => true,
        ]);
    }

    #[Route(path: '/api/_admin/check-customer-email-valid', name: 'api.admin.check-customer-email-valid', methods: ['POST'])]
    public function checkCustomerEmailValid(Request $request, Context $context): JsonResponse
    {
        $params = [];
        if (!$request->request->has('email')) {
            throw new \InvalidArgumentException('Parameter "email" is missing.');
        }

        $email = (string) $request->request->get('email');
        $isCustomerBoundSalesChannel = $this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $boundSalesChannelId = null;
        if ($isCustomerBoundSalesChannel) {
            $boundSalesChannelId = $request->request->get('boundSalesChannelId');
            if ($boundSalesChannelId !== null && !\is_string($boundSalesChannelId)) {
                throw RoutingException::invalidRequestParameter('boundSalesChannelId');
            }
        }

        $customer = $this->getCustomerByEmail((string) $request->request->get('id'), $email, $context, $boundSalesChannelId);
        if (!$customer) {
            return new JsonResponse(
                ['isValid' => true]
            );
        }

        $message = 'The email address {{ email }} is already in use';
        $params['{{ email }}'] = $email;

        if ($customer->getBoundSalesChannel()) {
            $message .= ' in the Sales Channel {{ salesChannel }}';
            $params['{{ salesChannel }}'] = $customer->getBoundSalesChannel()->getName();
        }

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            str_replace(array_keys($params), array_values($params), $message),
            $message,
            $params,
            null,
            null,
            $email,
            null,
            '79d30fe0-febf-421e-ac9b-1bfd5c9007f7'
        ));

        throw new ConstraintViolationException($violations, $request->request->all());
    }

    #[Route(path: '/api/_admin/sanitize-html', name: 'api.admin.sanitize-html', methods: ['POST'])]
    public function sanitizeHtml(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('html')) {
            throw new \InvalidArgumentException('Parameter "html" is missing.');
        }

        $html = (string) $request->request->get('html');
        $field = (string) $request->request->get('field');

        if ($field === '') {
            return new JsonResponse(
                ['preview' => $this->htmlSanitizer->sanitize($html)]
            );
        }

        [$entityName, $propertyName] = explode('.', $field);
        $property = $this->definitionInstanceRegistry->getByEntityName($entityName)->getField($propertyName);

        if ($property === null) {
            throw new \InvalidArgumentException('Invalid field property provided.');
        }

        $flag = $property->getFlag(AllowHtml::class);

        if ($flag === null) {
            return new JsonResponse(
                ['preview' => strip_tags($html)]
            );
        }

        if ($flag instanceof AllowHtml && !$flag->isSanitized()) {
            return new JsonResponse(
                ['preview' => $html]
            );
        }

        return new JsonResponse(
            ['preview' => $this->htmlSanitizer->sanitize($html, [], false, $field)]
        );
    }

    private function fetchLanguageIdByName(string $isoCode, Connection $connection): ?string
    {
        $languageId = $connection->fetchOne(
            '
            SELECT `language`.id FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` = :code',
            ['code' => $isoCode]
        );

        return $languageId === false ? null : Uuid::fromBytesToHex($languageId);
    }

    private function getLatestApiVersion(): ?int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);

        usort($sortedSupportedApiVersions, fn (int $version1, int $version2) => version_compare((string) $version1, (string) $version2));

        return array_pop($sortedSupportedApiVersions);
    }

    private function getCustomerByEmail(string $customerId, string $email, Context $context, ?string $boundSalesChannelId): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        if ($boundSalesChannelId) {
            $criteria->addAssociation('boundSalesChannel');
        }

        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('guest', false));
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('id', $customerId)]
        ));

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('boundSalesChannelId', null),
            new EqualsFilter('boundSalesChannelId', $boundSalesChannelId),
        ]));

        /** @var ?CustomerEntity $customer */
        $customer = $this->customerRepo->search($criteria, $context)->first();

        return $customer;
    }
}
