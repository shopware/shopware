<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class NewsletterConfirmRoute extends AbstractNewsletterConfirmRoute
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

    public function __construct(
        EntityRepositoryInterface $newsletterRecipientRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/newsletter/confirm",
     *      description="Confirm newsletter registration",
     *      operationId="confirmNewsletter",
     *      tags={"Store API", "Newsletter"},
     *      @OA\Parameter(name="hash", description="Hash from Mail", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="em", description="Hash from Mail", in="query", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *     )
     * )
     * @Route("/store-api/v{version}/newsletter/confirm", name="store-api.newsletter.confirm", methods={"POST"})
     */
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
            ->add('confirmedAt', new IsNull())
            ->add('status', new EqualTo(['value' => NewsletterSubscribeRoute::STATUS_NOT_SET]))
            ->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }
}
