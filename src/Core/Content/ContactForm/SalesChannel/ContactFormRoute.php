<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ContactFormRoute extends AbstractContactFormRoute
{
    private DataValidationFactoryInterface $contactFormValidationFactory;

    private DataValidator $validator;

    private EventDispatcherInterface $eventDispatcher;

    private SystemConfigService $systemConfigService;

    private EntityRepositoryInterface $cmsSlotRepository;

    private EntityRepositoryInterface $salutationRepository;

    private EntityRepositoryInterface $categoryRepository;

    private EntityRepositoryInterface $landingPageRepository;

    private EntityRepositoryInterface $productRepository;

    public function __construct(
        DataValidationFactoryInterface $contactFormValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $cmsSlotRepository,
        EntityRepositoryInterface $salutationRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $landingPageRepository,
        EntityRepositoryInterface $productRepository
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
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/contact-form",
     *      summary="Submit a contact form message",
     *      description="Used for submitting contact forms. Be aware that there can be more required fields, depending on the system settings.",
     *      operationId="sendContactMail",
     *      tags={"Store API", "Content"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "salutationId",
     *                  "email",
     *                  "subject",
     *                  "comment"
     *              },
     *              @OA\Property(
     *                  property="salutationId",
     *                  description="Identifier of the salutation. Use `/api/salutation` endpoint to fetch possible values.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="firstName",
     *                  description="Firstname. This field may be required depending on the system settings.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="lastName",
     *                  description="Lastname. This field may be required depending on the system settings.",
     *                  type="string"
     *              ),
     *              @OA\Property(property="email", description="Email address", type="string"),
     *              @OA\Property(
     *                  property="phone",
     *                  description="Phone. This field may be required depending on the system settings.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="subject",
     *                  description="The subject of the contact form.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="comment",
     *                  description="The message of the contact form",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="navigationId",
     *                  description="Identifier of the navigation page. Can be used to override the configuration.
Take a look at the settings of a category containing a concact form in the administration.",
     *                  type="string"),
     *              @OA\Property(
     *                  property="slotId",
     *                  description="Identifier of the cms element",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="cmsPageType",
     *                  description="Type of the content management page",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="entityName",
     *                  description="Entity name for slot config",
     *                  type="string"
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Message sent successful."
     *     )
     * )
     * @Route("/store-api/contact-form", name="store-api.contact.form", methods={"POST"})
     */
    public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse
    {
        $this->validateContactForm($data, $context);
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
