<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
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

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class ContactFormRoute extends AbstractContactFormRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $contactFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $cmsSlotRepository,
        private readonly EntityRepository $salutationRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $landingPageRepository,
        private readonly EntityRepository $productRepository,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/contact-form', name: 'store-api.contact.form', methods: ['POST'])]
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
        $mailConfigs = [];
        $mailConfigs['receivers'] = [];
        $mailConfigs['message'] = '';

        $criteria = new Criteria([$navigationId]);

        $entity = match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->productRepository->search($criteria, $context->getContext())->first(),
            LandingPageDefinition::ENTITY_NAME => $this->landingPageRepository->search($criteria, $context->getContext())->first(),
            default => $this->categoryRepository->search($criteria, $context->getContext())->first(),
        };

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
        $mailConfigs = [];
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
