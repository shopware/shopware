<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class NewsletterConfirmRoute extends AbstractNewsletterConfirmRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/newsletter/confirm', name: 'store-api.newsletter.confirm', methods: ['POST'])]
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse
    {
        $recipient = $this->getNewsletterRecipient('hash', $dataBag->get('hash', ''), $context->getContext());

        $data = [
            'id' => $recipient->getId(),
            'status' => $recipient->getStatus(),
            'confirmedAt' => $recipient->getConfirmedAt(),
            'em' => $dataBag->get('em'),
        ];

        $this->validator->validate($data, $this->getBeforeConfirmSubscribeValidation(hash('sha1', $recipient->getEmail())));

        $data['status'] = NewsletterSubscribeRoute::STATUS_OPT_IN;
        $data['confirmedAt'] = new \DateTime();

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
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

    private function getBeforeConfirmSubscribeValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_in_before');
        $definition->add('id', new NotBlank())
            ->add('status', new EqualTo(['value' => NewsletterSubscribeRoute::STATUS_NOT_SET]))
            ->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }
}
