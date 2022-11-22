<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package content
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class ContactFormRoute extends AbstractContactFormRoute
{
    private DataValidationFactoryInterface $contactFormValidationFactory;

    private DataValidator $validator;

    private EventDispatcherInterface $eventDispatcher;

    private SystemConfigService $systemConfigService;

    private EntityRepository $cmsSlotRepository;

    private EntityRepository $salutationRepository;

    private EntityRepository $categoryRepository;

    private EntityRepository $landingPageRepository;

    private EntityRepository $productRepository;

    private RequestStack $requestStack;

    private RateLimiter $rateLimiter;

    /**
     * @internal
     */
    public function __construct(
        DataValidationFactoryInterface $contactFormValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepository $cmsSlotRepository,
        EntityRepository $salutationRepository,
        EntityRepository $categoryRepository,
        EntityRepository $landingPageRepository,
        EntityRepository $productRepository,
        RequestStack $requestStack,
        RateLimiter $rateLimiter
    ) {
        $this->contactFormValidationFactory = $contactFormValidationFactory;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
        $this->cmsSlotRepository = $cmsSlotRepository;
        $this->salutationRepository = $salutationRepository;
        $this->categoryRepository = $categoryRepository;
        $this->landingPageRepository = $landingPageRepository;
        $this->productRepository = $productRepository;
        $this->requestStack = $requestStack;
        $this->rateLimiter = $rateLimiter;
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/store-api/contact-form", name="store-api.contact.form", methods={"POST"})
     */
    public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse
    {
        $this->validateContactForm($data, $context);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::CONTACT_FORM, $request->getClientIp());
        }

        $mailConfigs = $this->getMailConfigs($context, $data->get('slotId'), $data->get('navigationId'), $data->get('entityName'));

        $salutationCriteria = new Criteria([$data->get('salutationId')]);
        $salutationSearchResult = $this->salutationRepository->search($salutationCriteria, $context->getContext());

        if ($salutationSearchResult->count() !== 0) {
            $data->set('salutation', $salutationSearchResult->first());
        }

        if (empty($mailConfigs['receivers'])) {
            $mailConfigs['receivers'][] = $this->systemConfigService->get('core.basicInformation.email', $context->getSalesChannel()->getId());
        }

        $recipientStructs = [];
        foreach ($mailConfigs['receivers'] as $mail) {
            $recipientStructs[$mail] = $mail;
        }

        $event = new ContactFormEvent(
            $context->getContext(),
            $context->getSalesChannel()->getId(),
            new MailRecipientStruct($recipientStructs),
            $data
        );

        $this->eventDispatcher->dispatch(
            $event,
            ContactFormEvent::EVENT_NAME
        );

        $result = new ContactFormRouteResponseStruct();
        $result->assign([
            'individualSuccessMessage' => $mailConfigs['message'] ?? '',
        ]);

        return new ContactFormRouteResponse($result);
    }

    private function validateContactForm(DataBag $data, SalesChannelContext $context): void
    {
        $definition = $this->contactFormValidationFactory->create($context);
        $violations = $this->validator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function getSlotConfig(string $slotId, string $navigationId, SalesChannelContext $context, ?string $entityName = null): array
    {
        $mailConfigs['receivers'] = [];
        $mailConfigs['message'] = '';

        $criteria = new Criteria([$navigationId]);

        switch ($entityName) {
            case ProductDefinition::ENTITY_NAME:
                $entity = $this->productRepository->search($criteria, $context->getContext())->first();

                break;
            case LandingPageDefinition::ENTITY_NAME:
                $entity = $this->landingPageRepository->search($criteria, $context->getContext())->first();

                break;
            default:
                $entity = $this->categoryRepository->search($criteria, $context->getContext())->first();
        }

        if (!$entity) {
            return $mailConfigs;
        }

        if (empty($entity->getSlotConfig()[$slotId])) {
            return $mailConfigs;
        }

        $mailConfigs['receivers'] = $entity->getSlotConfig()[$slotId]['mailReceiver']['value'];
        $mailConfigs['message'] = $entity->getSlotConfig()[$slotId]['confirmationText']['value'];

        return $mailConfigs;
    }

    /**
     * @return array<string, array<string, array<int, mixed>|bool|float|int|string|null>|string|mixed>
     */
    private function getMailConfigs(SalesChannelContext $context, ?string $slotId = null, ?string $navigationId = null, ?string $entityName = null): array
    {
        $mailConfigs['receivers'] = [];
        $mailConfigs['message'] = '';

        if (!$slotId) {
            return $mailConfigs;
        }

        if ($navigationId) {
            $mailConfigs = $this->getSlotConfig($slotId, $navigationId, $context, $entityName);
            if (!empty($mailConfigs['receivers'])) {
                return $mailConfigs;
            }
        }

        $criteria = new Criteria([$slotId]);
        $slot = $this->cmsSlotRepository->search($criteria, $context->getContext());
        $mailConfigs['receivers'] = $slot->getEntities()->first()->getTranslated()['config']['mailReceiver']['value'];
        $mailConfigs['message'] = $slot->getEntities()->first()->getTranslated()['config']['confirmationText']['value'];

        return $mailConfigs;
    }
}
