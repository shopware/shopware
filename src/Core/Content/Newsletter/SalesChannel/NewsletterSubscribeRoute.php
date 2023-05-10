<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type SubscribeRequest array{email: string, storefrontUrl: string, option: string, firstName?: string, lastName?: string, zipCode?: string, city?: string, street?: string, salutationId?: string}
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
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
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestStack $requestStack
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

        $validator = $this->getOptInValidator($dataBag, $context, $validateStorefrontUrl);

        $this->validator->validate($dataBag->all(), $validator);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::NEWSLETTER_FORM, $request->getClientIp());
        }

        /** @var SubscribeRequest $data */
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
            'storefrontUrl'
        );

        $recipientId = $this->getNewsletterRecipientId($data['email'], $context);

        if (isset($recipientId)) {
            /** @var NewsletterRecipientEntity $recipient */
            $recipient = $this->newsletterRecipientRepository->search(new Criteria([$recipientId]), $context->getContext())->first();

            // If the user was previously subscribed but has unsubscribed now, the `getConfirmedAt()`
            // will still be set. So we need to check for the status as well.
            if ($recipient->getStatus() !== self::STATUS_OPT_OUT && $recipient->getConfirmedAt()) {
                return new NoContentResponse();
            }
        }

        $data = $this->completeData($data, $context);

        $this->newsletterRecipientRepository->upsert([$data], $context->getContext());

        $recipient = $this->getNewsletterRecipient('email', $data['email'], $context->getContext());

        if (!$this->isNewsletterDoi($context)) {
            $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
            $this->eventDispatcher->dispatch($event);

            return new NoContentResponse();
        }

        $hashedEmail = hash('sha1', $data['email']);
        $url = $this->getSubscribeUrl($context, $hashedEmail, $data['hash'], $data, $recipient);

        $event = new NewsletterRegisterEvent($context->getContext(), $recipient, $url, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }

    /**
     * @deprecated tag:v6.6.0 - reason:visibility-change - will be private in v6.6.0
     */
    public function isNewsletterDoi(SalesChannelContext $context): ?bool
    {
        if ($context->getCustomerId() === null) {
            return $this->systemConfigService->getBool('core.newsletter.doubleOptIn', $context->getSalesChannelId());
        }

        return $this->systemConfigService->getBool('core.newsletter.doubleOptInRegistered', $context->getSalesChannelId());
    }

    private function getOptInValidator(DataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.create');
        $definition->add('email', new NotBlank(), new Email())
            ->add('option', new NotBlank(), new Choice(array_keys($this->getOptionSelection($context))));

        if (!empty($dataBag->get('firstName'))) {
            $definition->add('firstName', new NotBlank(), new Regex([
                'pattern' => self::DOMAIN_NAME_REGEX,
                'match' => false,
            ]));
        }

        if (!empty($dataBag->get('lastName'))) {
            $definition->add('lastName', new NotBlank(), new Regex([
                'pattern' => self::DOMAIN_NAME_REGEX,
                'match' => false,
            ]));
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
     * @param SubscribeRequest $data
     *
     * @return array{id: string, languageId: string, salesChannelId: string, status: string, hash: string, email: string, storefrontUrl: string, firstName?: string, lastName?: string, zipCode?: string, city?: string, street?: string, salutationId?: string}
     */
    private function completeData(array $data, SalesChannelContext $context): array
    {
        $id = $this->getNewsletterRecipientId($data['email'], $context);

        $data['id'] = $id ?: Uuid::randomHex();
        $data['languageId'] = $context->getContext()->getLanguageId();
        $data['salesChannelId'] = $context->getSalesChannel()->getId();
        $data['status'] = $this->getOptionSelection($context)[$data['option']];
        $data['hash'] = Uuid::randomHex();

        return $data;
    }

    private function getNewsletterRecipientId(string $email, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('email', $email),
                new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
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
    private function getOptionSelection(SalesChannelContext $context): array
    {
        return [
            self::OPTION_DIRECT => $this->isNewsletterDoi($context) ? self::STATUS_NOT_SET : self::STATUS_DIRECT,
            self::OPTION_SUBSCRIBE => $this->isNewsletterDoi($context) ? self::STATUS_NOT_SET : self::STATUS_DIRECT,
            self::OPTION_CONFIRM_SUBSCRIBE => self::STATUS_OPT_IN,
            self::OPTION_UNSUBSCRIBE => self::STATUS_OPT_OUT,
        ];
    }

    private function getNewsletterRecipient(string $identifier, string $value, Context $context): NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $value));
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context)->getEntities()->first();

        if (empty($newsletterRecipient)) {
            throw new NewsletterRecipientNotFoundException($identifier, $value);
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
     * @param array{storefrontUrl: string} $data
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
