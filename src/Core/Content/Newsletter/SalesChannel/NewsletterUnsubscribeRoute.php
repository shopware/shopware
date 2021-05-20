<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class NewsletterUnsubscribeRoute extends AbstractNewsletterUnsubscribeRoute
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

    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/newsletter/unsubscribe",
     *      summary="Remove a newsletter subscription",
     *      description="Removes a newsletter recipient from the mailing lists.",
     *      operationId="unsubscribeToNewsletter",
     *      tags={"Store API", "Newsletter"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "email"
     *              },
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  description="Email address that should be removed from the mailing lists."
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Unsubscribing was successful.",
     *     )
     * )
     * @Route("/store-api/newsletter/unsubscribe", name="store-api.newsletter.unsubscribe", methods={"POST"})
     */
    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse
    {
        $data = $dataBag->only('email');
        $recipient = $this->getNewsletterRecipient($data['email'], $context);

        if (!$recipient) {
            throw new NewsletterRecipientNotFoundException('email', $data['email']);
        }

        $data['id'] = $recipient->getId();
        $data['status'] = NewsletterSubscribeRoute::STATUS_OPT_OUT;

        $validator = $this->getOptOutValidation();
        $this->validator->validate($data, $validator);

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        $event = new NewsletterUnsubscribeEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }

    private function getNewsletterRecipient(string $email, SalesChannelContext $context): ?NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND),
            new EqualsFilter('email', $email),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId())
        );
        $criteria->setLimit(1);

        return $this->newsletterRecipientRepository
            ->search($criteria, $context->getContext())
            ->first();
    }

    private function getOptOutValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_out');
        $definition->add('email', new NotBlank(), new Email())
            ->add('status', new EqualTo(['value' => NewsletterSubscribeRoute::STATUS_OPT_OUT]))
            ->add('id', new NotBlank());

        return $definition;
    }
}
