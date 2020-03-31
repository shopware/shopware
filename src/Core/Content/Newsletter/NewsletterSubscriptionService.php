<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterUpdateEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Content\Newsletter\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @deprecated tag:v6.3.0 use one of services:
 * \Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute
 * \Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute
 * \Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute
 */
class NewsletterSubscriptionService implements NewsletterSubscriptionServiceInterface
{
    /**
     * @var AbstractNewsletterSubscribeRoute
     */
    private $newsletterSubscribeRoute;

    /**
     * @var AbstractNewsletterConfirmRoute
     */
    private $newsletterConfirmRoute;

    /**
     * @var AbstractNewsletterUnsubscribeRoute
     */
    private $newsletterUnsubscribeRoute;

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
        AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        AbstractNewsletterUnsubscribeRoute $newsletterUnsubscribeRoute,
        EntityRepositoryInterface $newsletterRecipientRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $domainRepository
    ) {
        $this->newsletterSubscribeRoute = $newsletterSubscribeRoute;
        $this->newsletterConfirmRoute = $newsletterConfirmRoute;
        $this->newsletterUnsubscribeRoute = $newsletterUnsubscribeRoute;
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
        $requestDataBag = $dataBag->toRequestDataBag();

        if (!$requestDataBag->has('storefrontUrl')) {
            $requestDataBag->set('storefrontUrl', $this->getSubscribeUrl($context));
        }

        $this->newsletterSubscribeRoute->subscribe($requestDataBag, $context, false);
    }

    public function confirm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $this->newsletterConfirmRoute->confirm($dataBag->toRequestDataBag(), $context);
    }

    public function unsubscribe(DataBag $dataBag, SalesChannelContext $context): void
    {
        $this->newsletterUnsubscribeRoute->unsubscribe($dataBag->toRequestDataBag(), $context);
    }

    private function getUpdateValidator(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.update');
        $definition->add('email', new IsNull())
            ->add('id', new NotBlank());

        return $definition;
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

    private function getSubscribeUrl(SalesChannelContext $context): string
    {
        /** @var string $domain */
        $domain = $this->systemConfigService->get('core.newsletter.subscribeDomain', $context->getSalesChannel()->getId());

        if ($domain) {
            $url = $domain;
        } else {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
            $criteria->setLimit(1);

            $domainEntity = $this->domainRepository
                ->search($criteria, $context->getContext())
                ->first();

            if (!$domainEntity) {
                throw new SalesChannelDomainNotFoundException($context->getSalesChannel());
            }

            $url = $domainEntity->getUrl();
        }

        return $url;
    }
}
