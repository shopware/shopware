<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductReviewSaveRoute extends AbstractProductReviewSaveRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var SystemConfigService
     */
    private $config;

    public function __construct(
        EntityRepositoryInterface $reviewRepository,
        DataValidator $validator,
        SystemConfigService $config
    ) {
        $this->repository = $reviewRepository;
        $this->validator = $validator;
        $this->config = $config;
    }

    public function getDecorated(): AbstractProductReviewSaveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Post(
     *      path="/product/{productId}/review",
     *      summary="Save a product review",
     *      description="Saves a review for a product. Reviews have to be activated in the settings.",
     *      operationId="saveProductReview",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(
     *          name="productId",
     *          description="Identifier of the product which is reviewed.",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              required={
     *                  "title",
     *                  "content",
     *                  "points"
     *              },
     *              @OA\Property(
     *                  property="name",
     *                  type="string",
     *                  description="The name of the review author. If not set, the first name of the customer is chosen."
     *              ),
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  description="The email address of the review author. If not set, the email of the customer is chosen."
     *              ),
     *              @OA\Property(
     *                  property="title",
     *                  description="The title of the review.",
     *                  @OA\Schema(type="string", required=true, minLength=5)
     *              ),
     *              @OA\Property(
     *                  property="content",
     *                  description="The content of review.",
     *                  @OA\Schema(type="string", required=true, minLength=40)
     *              ),
     *              @OA\Property(
     *                  property="points",
     *                  description="The review rating for the product.",
     *                  @OA\Schema(type="integer", required=true, minimum=1, maximum=5)
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Success response indicating the review was saved successfuly."
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/product/{productId}/review", name="store-api.product-review.save", methods={"POST"})
     */
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
