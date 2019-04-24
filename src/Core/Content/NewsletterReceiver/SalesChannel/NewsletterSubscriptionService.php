<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\SalesChannel;

use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\NewsletterReceiver\Exception\NewsletterReceiverNotFoundException;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverEntity;
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
    private $newsletterReceiverRepository;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $newsletterReceiverRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->newsletterReceiverRepository = $newsletterReceiverRepository;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function subscribe(DataBag $dataBag, SalesChannelContext $context): void
    {
        $validator = $this->getOptInValidator();
        $this->validator->validate($dataBag->all(), $validator);

        $data = $this->completeData($dataBag->all(), $context);

        $this->newsletterReceiverRepository->upsert([$data], $context->getContext());

        $receiver = $this->getNewsletterReceiver('email', $data['email'], $context->getContext());

        if ($data['status'] === self::STATUS_DIRECT) {
            $event = new NewsletterConfirmEvent($context->getContext(), $receiver);
            $this->eventDispatcher->dispatch(NewsletterConfirmEvent::EVENT_NAME, $event);

            return;
        }

        $event = new NewsletterRegisterEvent($context->getContext(), $receiver, $data['url']);
        $this->eventDispatcher->dispatch(NewsletterRegisterEvent::EVENT_NAME, $event);
    }

    public function confirm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $receiver = $this->getNewsletterReceiver('hash', $dataBag->get('hash'), $context->getContext());

        $data = [
            'id' => $receiver->getId(),
            'status' => $receiver->getStatus(),
            'confirmedAt' => $receiver->getConfirmedAt(),
            'em' => $dataBag->get('em'),
        ];

        $this->validator->validate($data, $this->getBeforeConfirmSubscribeValidation(hash('sha1', $receiver->getEmail())));

        $data['status'] = self::STATUS_OPT_IN;
        $data['confirmedAt'] = new \DateTime();

        $this->newsletterReceiverRepository->update([$data], $context->getContext());

        $event = new NewsletterConfirmEvent($context->getContext(), $receiver);
        $this->eventDispatcher->dispatch(NewsletterConfirmEvent::EVENT_NAME, $event);
    }

    public function unsubscribe(DataBag $dataBag, SalesChannelContext $context): void
    {
        $data = $dataBag->all();
        $data['id'] = $this->getNewsletterReceiverId($data['email'], $context);

        if (empty($data['id'])) {
            throw new NewsletterReceiverNotFoundException('email', $data['email']);
        }

        $data['status'] = $this->getOptionSelection()[$data['option']];
        unset($data['salutationId']);

        $validator = $this->getOptOutValidation();
        $this->validator->validate($data, $validator);

        $this->newsletterReceiverRepository->update([$data], $context->getContext());
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
        $definition = new DataValidationDefinition('newsletter_receiver.create');
        $definition->add('email', new NotBlank(), new Email())
            ->add('option', new NotBlank(), new Choice(array_keys($this->getOptionSelection())));

        return $definition;
    }

    private function getOptOutValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_receiver.opt_out');
        $definition->add('email', new NotBlank(), new Email())
            ->add('status', new EqualTo(['value' => self::STATUS_OPT_OUT]))
            ->add('id', new NotBlank());

        return $definition;
    }

    private function getBeforeConfirmSubscribeValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_receiver.opt_in_before');
        $definition->add('id', new NotBlank())
            ->add('confirmedAt', new IsNull())
            ->add('status', new EqualTo(['value' => self::STATUS_NOT_SET]))
            ->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }

    private function completeData(array $data, SalesChannelContext $context): array
    {
        $id = $this->getNewsletterReceiverId($data['email'], $context);

        $data['id'] = $id ?: Uuid::randomHex();
        $data['languageId'] = $context->getContext()->getLanguageId();
        $data['salesChannelId'] = $context->getSalesChannel()->getId();
        $data['status'] = $this->getOptionSelection()[$data['option']];
        $data['hash'] = Uuid::randomHex();
        $data['url'] = sprintf(
            '%s/newsletter/subscribe?em=%s&hash=%s',
            $data['baseUrl'],
            hash('sha1', $data['email']),
            $data['hash']
        );

        return $data;
    }

    private function getNewsletterReceiverId(string $email, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND),
            new EqualsFilter('email', $email),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId())
        );
        $criteria->setLimit(1);

        $ids = $this->newsletterReceiverRepository->searchIds($criteria, $context->getContext())->getIds();

        return array_shift(
            $ids
        );
    }

    private function getNewsletterReceiver(string $identifier, string $value, Context $context): NewsletterReceiverEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($identifier, $value));
        $criteria->setLimit(1);

        $newsletterReceiver = $this->newsletterReceiverRepository->search($criteria, $context)->getEntities()->first();

        if (empty($newsletterReceiver)) {
            throw new NewsletterReceiverNotFoundException($identifier, $value);
        }

        return $newsletterReceiver;
    }
}
