<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductReviewService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $reviewRepository;

    /**
     * @var DataValidator
     */
    private $validator;

    public function __construct(
        EntityRepositoryInterface $reviewRepository,
        DataValidator $validator
    ) {
        $this->reviewRepository = $reviewRepository;
        $this->validator = $validator;
    }

    public function save(string $productId, DataBag $data, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            throw new CustomerNotLoggedInException();
        }

        $languageId = $context->getContext()->getLanguageId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $customerId = $customer->getId();

        if (!$data->has('name')) {
            $data->set('name', $customer->getFirstName());
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

        if ($data->get('id')) { // customer updates the review
            $review['id'] = $data->get('id');
            $this->reviewRepository->update([$review], $context->getContext());
        } else {
            $this->reviewRepository->create([$review], $context->getContext());
        }
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
}
