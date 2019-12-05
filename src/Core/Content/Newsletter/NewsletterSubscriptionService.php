<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUpdateEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Content\Newsletter\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotBlank;

class NewsletterSubscriptionService implements NewsletterSubscriptionServiceInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $newsletterRecipientRepository;

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
    private $domainRepository;

    public function __construct(
        EntityRepositoryInterface $newsletterRecipientRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $domainRepository
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
        $this->domainRepository = $domainRepository;
    }

    public function update(DataBag $dataBag, SalesChannelContext $context): void
    {
        $validator = $this->getUpdateValidator();
        $this->validator->validate($dataBag->all(), $validator);

        $data = $dataBag->only(
            'id',
            'title',
            'firstName',
            'lastName',
            'zipCode',
            'city',
            'street',
            'tags',
            'salutationId',
            'salutation',
            'languageId',
            'language'
        );

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $recipient = $this->getNewsletterRecipient('id', $data['id'], $context->getContext());

        $event = new NewsletterUpdateEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);
    }

    public function subscribe(DataBag $dataBag, SalesChannelContext $context): void
    {
        $validator = $this->getOptInValidator();
        $this->validator->validate($dataBag->all(), $validator);

        $data = $dataBag->only(
            'email',
            'title',
            'firstName',
            'lastName',
            'zipCode',
            'city',
            'street',
            'tags',
            'salutationId',
            'languageId',
            'option',
            'customFields'
        );

        $data = $this->completeData($data, $context);

        $this->newsletterRecipientRepository->create([$data], $context->getContext());

        $recipient = $this->getNewsletterRecipient('email', $data['email'], $context->getContext());

        if ($data['status'] === self::STATUS_DIRECT) {
            $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
            $this->eventDispatcher->dispatch($event);

            return;
        }

        $url = $this->getSubscribeUrl($context, $data);

        $event = new NewsletterRegisterEvent($context->getContext(), $recipient, $url, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);
    }

    public function confirm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $recipient = $this->getNewsletterRecipient('hash', $dataBag->get('hash'), $context->getContext());

        $data = [
            'id' => $recipient->getId(),
            'status' => $recipient->getStatus(),
            'confirmedAt' => $recipient->getConfirmedAt(),
            'em' => $dataBag->get('em'),
        ];

        $this->validator->validate($data, $this->getBeforeConfirmSubscribeValidation(hash('sha1', $recipient->getEmail())));

        $data['status'] = self::STATUS_OPT_IN;
        $data['confirmedAt'] = new \DateTime();

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);
    }

    public function unsubscribe(DataBag $dataBag, SalesChannelContext $context): void
    {
        $data = $dataBag->only('email', 'option');
        $data['id'] = $this->getNewsletterRecipientId($data['email'], $context);

        if (empty($data['id'])) {
            throw new NewsletterRecipientNotFoundException('email', $data['email']);
        }

        $data['status'] = $this->getOptionSelection()[$data['option']];
        unset($data['salutationId']);

        $validator = $this->getOptOutValidation();
        $this->validator->validate($data, $validator);

        $this->newsletterRecipientRepository->update([$data], $context->getContext());
    }

    private function getOptionSelection(): array
    {
        return [
            'direct' => self::STATUS_DIRECT,
            'subscribe' => self::STATUS_NOT_SET,
            'confirmSubscribe' => self::STATUS_OPT_IN,
            'unsubscribe' => self::STATUS_OPT_OUT,
        ];
    }

    private function getOptInValidator(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.create');
        $definition->add('email', new NotBlank(), new Email())
            ->add('option', new NotBlank(), new Choice(array_keys($this->getOptionSelection())));

        return $definition;
    }

    private function getOptOutValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_out');
        $definition->add('email', new NotBlank(), new Email())
            ->add('status', new EqualTo(['value' => self::STATUS_OPT_OUT]))
            ->add('id', new NotBlank());

        return $definition;
    }

    private function getUpdateValidator(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.update');
        $definition->add('email', new IsNull())
            ->add('id', new NotBlank());

        return $definition;
    }

    private function getBeforeConfirmSubscribeValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_in_before');
        $definition->add('id', new NotBlank())
            ->add('confirmedAt', new IsNull())
            ->add('status', new EqualTo(['value' => self::STATUS_NOT_SET]))
            ->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }

    private function completeData(array $data, SalesChannelContext $context): array
    {
        $id = $this->getNewsletterRecipientId($data['email'], $context);

        $data['id'] = $id ?: Uuid::randomHex();
        $data['languageId'] = $context->getContext()->getLanguageId();
        $data['salesChannelId'] = $context->getSalesChannel()->getId();
        $data['status'] = $this->getOptionSelection()[$data['option']];
        $data['hash'] = Uuid::randomHex();

        return $data;
    }

    private function getNewsletterRecipientId(string $email, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND),
            new EqualsFilter('email', $email),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId())
        );
        $criteria->setLimit(1);

        return $this->newsletterRecipientRepository
            ->searchIds($criteria, $context->getContext())
            ->firstId();
    }

    private function getNewsletterRecipient(string $identifier, string $value, Context $context): NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $value));
        $criteria->setLimit(1);

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context)->getEntities()->first();

        if (empty($newsletterRecipient)) {
            throw new NewsletterRecipientNotFoundException($identifier, $value);
        }

        return $newsletterRecipient;
    }

    private function getSubscribeUrl(SalesChannelContext $context, array $data): string
    {
        $domain = $this->systemConfigService->get('core.newsletter.subscribeDomain', $context->getSalesChannel()->getId());

        if ($domain) {
            $url = $domain;
        } else {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
            $criteria->setLimit(1);

            $domain = $this->domainRepository
                ->search($criteria, $context->getContext())
                ->first();

            if (!$domain) {
                throw new SalesChannelDomainNotFoundException($context->getSalesChannel());
            }

            $url = $domain->getUrl();
        }

        $url .= str_replace(
            [
                '%%HASHEDEMAIL%%',
                '%%SUBSCRIBEHASH%%',
            ],
            [
                hash('sha1', $data['email']),
                $data['hash'],
            ],
            '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%'
        );

        return $url;
    }
}
