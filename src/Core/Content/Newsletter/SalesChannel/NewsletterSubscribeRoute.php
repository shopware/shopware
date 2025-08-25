<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('after-sales')]
class NewsletterSubscribeRoute extends AbstractNewsletterSubscribeRoute
{
    final public const STATUS_NOT_SET = 'notSet';
    final public const STATUS_OPT_IN = 'optIn';
    final public const STATUS_OPT_OUT = 'optOut';
    final public const STATUS_DIRECT = 'direct';

    /**
     * The subscription is directly active and does not need a confirmation.
     */
    final public const OPTION_DIRECT = 'direct';

    /**
     * An email will be send to the provided email addrees containing a link to the /newsletter/confirm route.
     */
    final public const OPTION_SUBSCRIBE = 'subscribe';

    /**
     * The email address will be removed from the newsletter subscriptions.
     */
    final public const OPTION_UNSUBSCRIBE = 'unsubscribe';

    /**
     * Confirms the newsletter subscription for the provided email address.
     */
    final public const OPTION_CONFIRM_SUBSCRIBE = 'confirmSubscribe';

    /**
     * The regex to check if string contains an url
     */
    final public const DOMAIN_NAME_REGEX = '/((https?:\/))/';

    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestStack $requestStack,
        private readonly StoreApiCustomFieldMapper $customFieldMapper,
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/newsletter/subscribe', name: 'store-api.newsletter.subscribe', methods: ['POST'])]
    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl = true): NoContentResponse
    {
        $doubleOptInDomain = $this->systemConfigService->getString(
            'core.newsletter.doubleOptInDomain',
            $context->getSalesChannelId()
        );
        if ($doubleOptInDomain !== '') {
            $dataBag->set('storefrontUrl', $doubleOptInDomain);
            $validateStorefrontUrl = false;
        }

        EmailIdnConverter::encodeDataBag($dataBag);

        $validator = $this->getOptInValidator($dataBag, $context, $validateStorefrontUrl);

        $this->validator->validate($dataBag->all(), $validator);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::NEWSLETTER_FORM, $request->getClientIp());
            } catch (RateLimitExceededException $e) {
                throw NewsletterException::newsletterThrottled($e->getWaitTime());
            }
        }

        $data = $dataBag->only(
            'email',
            'title',
            'firstName',
            'lastName',
            'zipCode',
            'city',
            'street',
            'salutationId',
            'option',
            'storefrontUrl',
            'customFields'
        );

        $recipientId = $this->getNewsletterRecipientId($data['email'], $context);

        if ($recipientId !== null) {
            $recipient = $this->newsletterRecipientRepository->search(new Criteria([$recipientId]), $context->getContext())->first();
            \assert($recipient instanceof NewsletterRecipientEntity);

            // If the user was previously subscribed but has unsubscribed now, the `getConfirmedAt()`
            // will still be set. So we need to check for the status as well.
            if ($recipient->getStatus() !== self::STATUS_OPT_OUT && $recipient->getConfirmedAt()) {
                return new NoContentResponse();
            }
        }

        $data = $this->completeData($data, $context, $recipientId);
        if ($dataBag->get('customFields') instanceof RequestDataBag) {
            $data['customFields'] = $this->customFieldMapper->map(
                NewsletterRecipientDefinition::ENTITY_NAME,
                $dataBag->get('customFields')
            );
            if ($data['customFields'] === []) {
                unset($data['customFields']);
            }
        }

        $this->newsletterRecipientRepository->upsert([$data], $context->getContext());

        $recipient = $this->getNewsletterRecipient($data['email'], $context);
        $recipientEmail = $recipient->getEmail();

        if (!$this->isNewsletterDoi($context, $recipientEmail)) {
            $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannelId());
            $this->eventDispatcher->dispatch($event);

            return new NoContentResponse();
        }

        $hashedEmail = Hasher::hash($data['email'], 'sha1');
        $url = $this->getSubscribeUrl($context, $hashedEmail, $data['hash'], $data, $recipient);

        $event = new NewsletterRegisterEvent($context->getContext(), $recipient, $url, $context->getSalesChannelId());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }

    /**
     * Determines if double opt-in (DOI) is required for newsletter subscription.
     *
     * For guest users: use general DOI setting
     * For logged-in users:
     * If DOI for registered customers is enabled: always require DOI
     * If DOI for registered customers is disabled and general DOI is disabled: never require DOI
     * If DOI for registered customers is disabled and general DOI is enabled: require DOI if the recipient email is different from the customer's email
     */
    private function isNewsletterDoi(SalesChannelContext $context, ?string $recipientEmail): bool
    {
        $salesChannelId = $context->getSalesChannelId();
        $customerId = $context->getCustomerId();
        $isDoubleOptIn = $this->systemConfigService->getBool('core.newsletter.doubleOptIn', $salesChannelId);
        $isDoubleOptInRegistered = $this->systemConfigService->getBool('core.newsletter.doubleOptInRegistered', $salesChannelId);

        if ($customerId === null) {
            return $isDoubleOptIn;
        }

        if ($isDoubleOptInRegistered) {
            return true;
        }

        if (!$isDoubleOptIn) {
            return false;
        }

        $customerEmail = $this->getCustomerEmail($context, $customerId);

        return $customerEmail !== $recipientEmail;
    }

    private function getCustomerEmail(SalesChannelContext $context, string $customerId): ?string
    {
        $criteria = new Criteria([$customerId]);

        $customer = $this->customerRepository->search($criteria, $context->getContext())->getEntities()->first();

        return $customer?->getEmail();
    }

    private function getOptInValidator(DataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.create');
        $definition->add('email', new NotBlank(), new Email())
            ->add('option', new NotBlank(), new Choice(array_keys($this->getOptionSelection($context, $dataBag->get('email')))));

        if (!empty($dataBag->get('firstName'))) {
            $definition->add('firstName', new NotBlank(), new Regex(pattern: self::DOMAIN_NAME_REGEX, match: false));
        }

        if (!empty($dataBag->get('lastName'))) {
            $definition->add('lastName', new NotBlank(), new Regex(pattern: self::DOMAIN_NAME_REGEX, match: false));
        }

        if ($validateStorefrontUrl) {
            $definition
                ->add('storefrontUrl', new NotBlank(), new Choice(array_values($this->getDomainUrls($context))));
        }

        $validationEvent = new BuildValidationEvent($definition, $dataBag, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $definition;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function completeData(array $data, SalesChannelContext $context, ?string $recipientId): array
    {
        $data['id'] = $recipientId ?? Uuid::randomHex();
        $data['languageId'] = $context->getLanguageId();
        $data['salesChannelId'] = $context->getSalesChannelId();
        $data['status'] = $this->getOptionSelection($context, $data['email'])[$data['option']];
        $data['hash'] = Uuid::randomHex();

        return $data;
    }

    private function getNewsletterRecipientId(string $email, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('email', $email),
                new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            ]),
        );
        $criteria->setLimit(1);

        return $this->newsletterRecipientRepository
            ->searchIds($criteria, $context->getContext())
            ->firstId();
    }

    /**
     * @return array<string, string>
     */
    private function getOptionSelection(SalesChannelContext $context, ?string $recipientEmail): array
    {
        return [
            self::OPTION_DIRECT => $this->isNewsletterDoi($context, $recipientEmail) ? self::STATUS_NOT_SET : self::STATUS_DIRECT,
            self::OPTION_SUBSCRIBE => $this->isNewsletterDoi($context, $recipientEmail) ? self::STATUS_NOT_SET : self::STATUS_DIRECT,
            self::OPTION_CONFIRM_SUBSCRIBE => self::STATUS_OPT_IN,
            self::OPTION_UNSUBSCRIBE => self::STATUS_OPT_OUT,
        ];
    }

    private function getNewsletterRecipient(string $email, SalesChannelContext $context): NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$newsletterRecipient) {
            throw NewsletterException::recipientNotFound('email', $email);
        }

        return $newsletterRecipient;
    }

    /**
     * @return string[]
     */
    private function getDomainUrls(SalesChannelContext $context): array
    {
        $salesChannelDomainCollection = $context->getSalesChannel()->getDomains();
        if ($salesChannelDomainCollection === null) {
            return [];
        }

        return array_map(static fn (SalesChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $salesChannelDomainCollection->getElements());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getSubscribeUrl(
        SalesChannelContext $context,
        string $hashedEmail,
        string $hash,
        array $data,
        NewsletterRecipientEntity $recipient
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.newsletter.subscribeUrl',
            $context->getSalesChannelId()
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';
        }

        $urlEvent = new NewsletterSubscribeUrlEvent($context, $urlTemplate, $hashedEmail, $hash, $data, $recipient);
        $this->eventDispatcher->dispatch($urlEvent);

        return $data['storefrontUrl'] . str_replace(
            [
                '%%HASHEDEMAIL%%',
                '%%SUBSCRIBEHASH%%',
            ],
            [
                $hashedEmail,
                $hash,
            ],
            $urlEvent->getSubscribeUrl()
        );
    }
}
