<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @OA\Post(
     *      path="/newsletter/unsubscribe",
     *      description="Unsubscribe to newsletter",
     *      operationId="unsubscribeToNewsletter",
     *      tags={"Store API", "Newsletter"},
     *      @OA\Parameter(name="email", description="Email", in="query", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *     )
     * )
     * @Route("/store-api/v{version}/newsletter/unsubscribe", name="store-api.newsletter.unsubscribe", methods={"POST"})
     */
    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse
    {
        $data = $dataBag->only('email');
        $data['id'] = $this->getNewsletterRecipientId($data['email'], $context);

        if (empty($data['id'])) {
            throw new NewsletterRecipientNotFoundException('email', $data['email']);
        }

        $data['status'] = NewsletterSubscribeRoute::STATUS_OPT_OUT;

        $validator = $this->getOptOutValidation();
        $this->validator->validate($data, $validator);

        $this->newsletterRecipientRepository->update([$data], $context->getContext());

        return new NoContentResponse();
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

    private function getOptOutValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.opt_out');
        $definition->add('email', new NotBlank(), new Email())
            ->add('status', new EqualTo(['value' => NewsletterSubscribeRoute::STATUS_OPT_OUT]))
            ->add('id', new NotBlank());

        return $definition;
    }
}
