<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContactFormRoute extends AbstractContactFormRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $contactFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $salutationRepository,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly CmsFormSlotConfigResolver $cmsFormSlotConfigResolver,
    ) {
    }

    public function getDecorated(): AbstractContactFormRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/contact-form', name: 'store-api.contact.form', methods: ['POST'])]
    public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse
    {
        EmailIdnConverter::encodeDataBag($data);

        $this->validateContactForm($data, $context);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::CONTACT_FORM, $request->getClientIp());
        }

        $mailConfigs = $this->cmsFormSlotConfigResolver->resolve($context, $data->get('slotId'), $data->get('navigationId'), $data->get('entityName'));

        $salutationCriteria = new Criteria([$data->get('salutationId')]);
        $salutationSearchResult = $this->salutationRepository->search($salutationCriteria, $context->getContext());

        if ($salutationSearchResult->getEntities()->count() !== 0) {
            $data->set('salutation', $salutationSearchResult->getEntities()->first());
        }

        $event = new ContactFormEvent(
            $context->getContext(),
            $context->getSalesChannelId(),
            new MailRecipientStruct($mailConfigs['receivers']),
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
}
