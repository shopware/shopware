<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
    /**
     * @var DataValidationFactoryInterface
     */
    private $contactFormValidationFactory;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepositoryInterface
     */
    private $cmsSlotRepository;

    public function __construct(
        DataValidationFactoryInterface $contactFormValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $cmsSlotRepository
    ) {
        $this->contactFormValidationFactory = $contactFormValidationFactory;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
        $this->cmsSlotRepository = $cmsSlotRepository;
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/contact-form",
     *      description="Send message throught contact form",
     *      operationId="sendContactMail",
     *      tags={"Store API", "Contact Mail"},
     *      @OA\Parameter(name="salutationId", description="Salutation ID", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="firstName", description="Firstname", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="lastName", description="Lastname", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="email", description="Email", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="phone", description="Phone", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="subject", description="Title", in="body", @OA\Schema(type="string")),
     *      @OA\Parameter(name="comment", description="Message", in="body", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Message sent"
     *     )
     * )
     * @Route("/store-api/v{version}/contact-form", name="store-api.contact.form", methods={"POST"})
     */
    public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse
    {
        $receivers = [];
        $message = '';
        if ($data->has('slotId')) {
            $slotId = $data->get('slotId');

            if ($slotId) {
                $criteria = new Criteria([$slotId]);
                $slot = $this->cmsSlotRepository->search($criteria, $context->getContext());
                $receivers = $slot->getEntities()->first()->get('config')['mailReceiver']['value'];
                $message = $slot->getEntities()->first()->get('config')['confirmationText']['value'];
            }
        }

        if (empty($receivers)) {
            $receivers[] = $this->systemConfigService->get('core.basicInformation.email', $context->getSalesChannel()->getId());
        }

        $this->validateContactForm($data, $context);

        foreach ($receivers as $mail) {
            $event = new ContactFormEvent(
                $context->getContext(),
                $context->getSalesChannel()->getId(),
                new MailRecipientStruct([$mail => $mail]),
                $data
            );

            $this->eventDispatcher->dispatch(
                $event,
                ContactFormEvent::EVENT_NAME
            );
        }

        $result = new ContactFormRouteResponseStruct();
        $result->assign([
            'individualSuccessMessage' => $message ?? '',
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
}
