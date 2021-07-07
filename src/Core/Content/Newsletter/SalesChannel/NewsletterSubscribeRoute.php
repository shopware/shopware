<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    private SystemConfigService $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $newsletterRecipientRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/newsletter/subscribe",
     *      summary="Create or remove a newsletter subscription",
     *      description="This route is used to create/remove/confirm a newsletter subscription.

The `option` property controls what should happen:
* `direct`: The subscription is directly active and does not need a confirmation.
* `subscribe`: An email will be send to the provided email addrees containing a link to the /newsletter/confirm route.
The subscription is only successful, if the /newsletter/confirm route is called with the generated hashes.
* `unsubscribe`: The email address will be removed from the newsletter subscriptions.
* `confirmSubscribe`: Confirmes the newsletter subscription for the provided email address.",
     *      operationId="subscribeToNewsletter",
     *      tags={"Store API", "Newsletter"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "email",
     *                  "option",
     *                  "storefrontUrl"
     *              },
     *              @OA\Property(
     *                  property="email",
     *                  description="Email address that will receive the confirmation and the newsletter.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="option",
     *                  description="Defines what should be done.",
     *                  @OA\Schema(type="string", enum={"direct", "subscribe", "confirmSubscribe", "unsubscribe"})
     *              ),
     *              @OA\Property(
     *                  property="storefrontUrl",
     *                  description="Url of the storefront of the shop. This will be used for generating the link to the /newsletter/confirm inside the confirm email.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="salutationId",
     *                  description="Identifier of the salutation.",
     *                  @OA\Schema(type="string", pattern="^[0-9a-f]{32}$")
     *              ),
     *              @OA\Property(
     *                  property="firstName",
     *                  description="First name",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="lastName",
     *                  description="Last name",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="street",
     *                  description="Street",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="city",
     *                  description="City",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="zipCode",
     *                  description="Zip code",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="tags",
     *                  description="Zip code",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="languageId",
     *                  description="Identifier of the language.",
     *                  @OA\Schema(type="string", pattern="^[0-9a-f]{32}$")
     *              ),
     *              @OA\Property(
     *                  property="customFields",
     *                  description="Custom field data that should be added to the subscription.",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *     )
     * )
     * @Route("/store-api/newsletter/subscribe", name="store-api.newsletter.subscribe", methods={"POST"})
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

        if ($data['status'] === self::STATUS_DIRECT) {
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
            return rtrim($domainEntity->getUrl(), '/');
        }, $context->getSalesChannel()->getDomains()->getElements());
    }

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
