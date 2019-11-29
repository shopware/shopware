<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm;

use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContactFormService
{
    /**
     * @var ValidationServiceInterface
     */
    private $contactFormValidationService;

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
        ValidationServiceInterface $contactFormValidationService,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $cmsSlotRepository
    ) {
        $this->contactFormValidationService = $contactFormValidationService;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
        $this->cmsSlotRepository = $cmsSlotRepository;
    }

    public function sendContactForm(DataBag $data, SalesChannelContext $context): string
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
            $receivers[] = $this->systemConfigService->get('core.basicInformation.email');
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

        return $message;
    }

    private function validateContactForm(DataBag $data, SalesChannelContext $context): void
    {
        $definition = $this->contactFormValidationService->buildCreateValidation($context->getContext());
        $violations = $this->validator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }
}
