<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class NewsletterSubscribeRoute extends AbstractNewsletterSubscribeRoute
{
    public const STATUS_NOT_SET = 'notSet';
    public const STATUS_OPT_IN = 'optIn';
    public const STATUS_OPT_OUT = 'optOut';
    public const STATUS_DIRECT = 'direct';

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

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/newsletter/subscribe",
     *      description="Subscribe to newsletter",
     *      operationId="subscribeToNewsletter",
     *      tags={"Store API", "Newsletter"},
     *      @OA\Parameter(name="email", description="Email", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="salutationId", description="Salutation", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="firstName", description="Firstname", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="lastName", description="Lastname", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="street", description="Street", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="city", description="City", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="zipCode", description="Zipcode", in="query", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *     )
     * )
     * @Route("/store-api/v{version}/newsletter/subscribe", name="store-api.newsletter.subscribe", methods={"POST"})
     */
    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl = true): NoContentResponse
    {
        $validator = $this->getOptInValidator($context, $validateStorefrontUrl);
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
            'customFields',
            'storefrontUrl'
        );

        $data = $this->completeData($data, $context);

        $this->newsletterRecipientRepository->upsert([$data], $context->getContext());

        $recipient = $this->getNewsletterRecipient('email', $data['email'], $context->getContext());

        if ($data['status'] === self::STATUS_DIRECT) {
            $event = new NewsletterConfirmEvent($context->getContext(), $recipient, $context->getSalesChannel()->getId());
            $this->eventDispatcher->dispatch($event);

            return new NoContentResponse();
        }

        $url = $data['storefrontUrl'] . str_replace(
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

        $event = new NewsletterRegisterEvent($context->getContext(), $recipient, $url, $context->getSalesChannel()->getId());
        $this->eventDispatcher->dispatch($event);

        return new NoContentResponse();
    }

    private function getOptInValidator(SalesChannelContext $context, bool $validateStorefrontUrl): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('newsletter_recipient.create');
        $definition->add('email', new NotBlank(), new Email())
            ->add('option', new NotBlank(), new Choice(array_keys($this->getOptionSelection())));

        if ($validateStorefrontUrl) {
            $definition
                ->add('storefrontUrl', new NotBlank(), new Choice(array_values($this->getDomainUrls($context))));
        }

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

    private function getOptionSelection(): array
    {
        return [
            'direct' => self::STATUS_DIRECT,
            'subscribe' => self::STATUS_NOT_SET,
            'confirmSubscribe' => self::STATUS_OPT_IN,
            'unsubscribe' => self::STATUS_OPT_OUT,
        ];
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

    private function getDomainUrls(SalesChannelContext $context): array
    {
        return array_map(static function (SalesChannelDomainEntity $domainEntity) {
            return $domainEntity->getUrl();
        }, $context->getSalesChannel()->getDomains()->getElements());
    }
}
