<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class ProductReviewSaveRoute extends AbstractProductReviewSaveRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly DataValidator $validator,
        private readonly SystemConfigService $config,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractProductReviewSaveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/product/{productId}/review', name: 'store-api.product-review.save', methods: ['POST'], defaults: ['_loginRequired' => true])]
    public function save(string $productId, RequestDataBag $data, SalesChannelContext $context): NoContentResponse
    {
        $this->checkReviewsActive($context);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $languageId = $context->getContext()->getLanguageId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $customerId = $customer->getId();

        if (!$data->has('name')) {
            $data->set('name', $customer->getFirstName());
        }

        if (!$data->has('lastName')) {
            $data->set('lastName', $customer->getLastName());
        }

        if (!$data->has('email')) {
            $data->set('email', $customer->getEmail());
        }

        $data->set('customerId', $customerId);
        $data->set('productId', $productId);
        $this->validate($data, $context->getContext());

        $review = [
            'productId' => $productId,
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'externalUser' => $data->get('name'),
            'externalEmail' => $data->get('email'),
            'title' => $data->get('title'),
            'content' => $data->get('content'),
            'points' => $data->get('points'),
            'status' => false,
        ];

        if ($data->get('id')) {
            $review['id'] = $data->get('id');
        }

        $this->repository->upsert([$review], $context->getContext());

        $mail = $this->config->get('core.basicInformation.email', $context->getSalesChannel()->getId());
        $mail = \is_string($mail) ? $mail : '';
        $event = new ReviewFormEvent(
            $context->getContext(),
            $context->getSalesChannel()->getId(),
            new MailRecipientStruct([$mail => $mail]),
            $data,
            $productId,
            $customerId
        );

        $this->eventDispatcher->dispatch(
            $event,
            ReviewFormEvent::EVENT_NAME
        );

        return new NoContentResponse();
    }

    private function validate(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('product.create_rating');

        $definition->add('name', new NotBlank());
        $definition->add('title', new NotBlank(), new Length(['min' => 5]));
        $definition->add('content', new NotBlank(), new Length(['min' => 40]));

        $definition->add('points', new GreaterThanOrEqual(1), new LessThanOrEqual(5));

        if ($data->get('id')) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerId', $data->get('customerId')));
            $criteria->addFilter(new EqualsFilter('id', $data->get('id')));

            $definition->add('id', new EntityExists([
                'entity' => 'product_review',
                'context' => $context,
                'criteria' => $criteria,
            ]));
        } else {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerId', $data->get('customerId')));
            $criteria->addFilter(new EqualsFilter('productId', $data->get('productId')));

            $definition->add('customerId', new EntityNotExists([
                'entity' => 'product_review',
                'context' => $context,
                'criteria' => $criteria,
            ]));
        }

        $this->validator->validate($data->all(), $definition);

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    /**
     * @throws ReviewNotActiveExeption
     */
    private function checkReviewsActive(SalesChannelContext $context): void
    {
        $showReview = $this->config->get('core.listing.showReview', $context->getSalesChannel()->getId());

        if (!$showReview) {
            throw new ReviewNotActiveExeption();
        }
    }
}
