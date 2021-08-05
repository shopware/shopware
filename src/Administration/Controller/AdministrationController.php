<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Events\PreResetExcludedSearchTermEvent;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdministrationController extends AbstractController
{
    private TemplateFinder $finder;

    private FirstRunWizardClient $firstRunWizardClient;

    private SnippetFinderInterface $snippetFinder;

    private array $supportedApiVersions;

    private KnownIpsCollectorInterface $knownIpsCollector;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private string $shopwareCoreDir;

    private EntityRepositoryInterface $customerRepo;

    private EntityRepositoryInterface $currencyRepository;

    private HtmlSanitizer $htmlSanitizer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    public function __construct(
        TemplateFinder $finder,
        FirstRunWizardClient $firstRunWizardClient,
        SnippetFinderInterface $snippetFinder,
        array $supportedApiVersions,
        KnownIpsCollectorInterface $knownIpsCollector,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        string $shopwareCoreDir,
        EntityRepositoryInterface $customerRepo,
        EntityRepositoryInterface $currencyRepository,
        HtmlSanitizer $htmlSanitizer,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->finder = $finder;
        $this->firstRunWizardClient = $firstRunWizardClient;
        $this->snippetFinder = $snippetFinder;
        $this->supportedApiVersions = $supportedApiVersions;
        $this->knownIpsCollector = $knownIpsCollector;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->shopwareCoreDir = $shopwareCoreDir;
        $this->customerRepo = $customerRepo;
        $this->currencyRepository = $currencyRepository;
        $this->htmlSanitizer = $htmlSanitizer;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    /**
     * @Since("6.3.3.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/%shopware_administration.path_name%", defaults={"auth_required"=false}, name="administration.index", methods={"GET"})
     */
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
            'systemCurrencyISOCode' => $defaultCurrency->getIsoCode(),
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardClient->frwShouldRun(),
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE),
        ]);
    }

    /**
     * @Since("6.1.0.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/snippets", name="api.admin.snippets", methods={"GET"})
     */
    public function snippets(Request $request): Response
    {
        $locale = $request->query->get('locale', 'en-GB');
        $snippets[$locale] = $this->snippetFinder->findSnippets((string) $locale);

        if ($locale !== 'en-GB') {
            $snippets['en-GB'] = $this->snippetFinder->findSnippets('en-GB');
        }

        return new JsonResponse($snippets);
    }

    /**
     * @Since("6.3.1.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/known-ips", name="api.admin.known-ips", methods={"GET"})
     */
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

    /**
     * @Since("6.4.0.1")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/reset-excluded-search-term", name="api.admin.reset-excluded-search-term", methods={"POST"})
     * @Acl({"system_config:update", "system_config:create", "system_config:delete"})
     *
     * @throws LanguageNotFoundException|\Doctrine\DBAL\DBALException
     *
     * @return JsonResponse
     */
    public function resetExcludedSearchTerm(Context $context)
    {
        $searchConfigId = $this->connection->fetchColumn('SELECT id FROM product_search_config WHERE language_id = :language_id', ['language_id' => Uuid::fromHexToBytes($context->getLanguageId())]);

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

        $this->connection->executeUpdate(
            'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
            [
                'excludedTerms' => json_encode($defaultExcludedTerm),
                'id' => $searchConfigId,
            ]
        );

        return new JsonResponse([
            'success' => true,
        ]);
    }

    /**
     * @Since("6.4.0.1")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/check-customer-email-valid", name="api.admin.check-customer-email-valid", methods={"POST"})
     *
     * @throws \InvalidArgumentException|ConstraintViolationException
     */
    public function checkCustomerEmailValid(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('email')) {
            throw new \InvalidArgumentException('Parameter "email" is missing.');
        }

        $email = (string) $request->request->get('email');
        $boundSalesChannelId = $request->request->get('bound_sales_channel_id');

        if ($boundSalesChannelId !== null && !\is_string($boundSalesChannelId)) {
            throw new InvalidRequestParameterException('bound_sales_channel_id');
        }

        if ($this->isEmailValid((string) $request->request->get('id'), $email, $context, $boundSalesChannelId)) {
            return new JsonResponse(
                ['isValid' => true]
            );
        }

        $message = 'The email address {{ email }} is already in use';
        $params['{{ email }}'] = $email;

        if ($boundSalesChannelId !== null) {
            $message .= ' in the sales channel {{ salesChannelId }}';
            $params['{{ salesChannelId }}'] = $boundSalesChannelId;
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

    /**
     * @Since("6.4.2.0")
     * @RouteScope(scopes={"administration"})
     * @Route("/api/_admin/sanitize-html", name="api.admin.sanitize-html", methods={"POST"})
     *
     * @throws \InvalidArgumentException
     */
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

        list($entityName, $propertyName) = explode('.', $field);
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
        $languageId = $connection->fetchColumn(
            '
            SELECT `language`.id FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` = :code',
            ['code' => $isoCode]
        );

        return $languageId === false ? null : Uuid::fromBytesToHex($languageId);
    }

    private function getLatestApiVersion(): int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);
        usort($sortedSupportedApiVersions, 'version_compare');

        return array_pop($sortedSupportedApiVersions);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function isEmailValid(string $customerId, string $email, Context $context, ?string $boundSalesChannelId): bool
    {
        $criteria = new Criteria();
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

        return $this->customerRepo->searchIds($criteria, $context)->getTotal() === 0;
    }
}
