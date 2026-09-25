<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ReviewsComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The single load is the whole point: the product page declares one reviews requirement as root-scoped
     * context, so every review element reads that one result instead of resolving the loader itself.
     */
    public function testProductPageLoadsReviewsOnceAsRootContext(): void
    {
        $definition = static::getContainer()->get(ProductContentLayoutDefinition::class);
        static::assertInstanceOf(ProductContentLayoutDefinition::class, $definition);

        $reviewRequirements = array_values(array_filter(
            $definition->getPageDataRequirements(),
            static fn (DataRequirement $requirement): bool => $requirement->source === 'product_review',
        ));

        static::assertCount(1, $reviewRequirements);
        static::assertSame('reviews', $reviewRequirements[0]->key);
    }

    /**
     * If any element grew its own resolvedBy binding it would load the reviews a second time, defeating the
     * shared root-scoped load. None of them may.
     */
    public function testNoReviewElementResolvesReviewsOnItsOwn(): void
    {
        foreach (['Sw:Product:Reviews', 'Sw:Product:ReviewSummary', 'Sw:Product:ReviewFilters'] as $type) {
            static::assertArrayNotHasKey('core:' . $type, $this->bindings()->all(), $type);
        }
    }

    public function testAllThreeElementsConsumeTheRootReviews(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        foreach (['Sw:Product:Reviews', 'Sw:Product:ReviewSummary', 'Sw:Product:ReviewFilters'] as $type) {
            $reviews = $types->get($type)->properties()['reviews'] ?? null;
            static::assertNotNull($reviews, $type);

            $schemaType = $reviews->toSchema()['type'];
            static::assertIsString($schemaType, $type);
            static::assertStringContainsString('ProductReviewResult', $schemaType, $type);
        }
    }

    public function testReviewsElementRendersTheListAndLeavesSummaryAndFiltersToTheirSlots(): void
    {
        $entities = new ProductReviewCollection([
            $this->review(5.0, 'Great product', 'Really loved it'),
            $this->review(4.0, 'Pretty good', 'Works as described', 'Thanks for your feedback'),
        ]);

        $html = $this->render('Sw:Product:Reviews', $this->reviewResult($this->matrix(five: 3, four: 1), $entities, total: 4, totalInCurrentLanguage: 4));

        static::assertStringContainsString('data-component="Sw:Product:Reviews"', $html);
        static::assertSame(2, substr_count($html, 'class="sw-product-reviews__item"'));
        static::assertStringContainsString('Great product', $html);
        static::assertStringContainsString('Works as described', $html);

        // Summary and filters are their own elements now, not part of this one.
        static::assertStringNotContainsString('sw-product-reviews__summary', $html);
        static::assertStringNotContainsString('js-reviews-sort', $html);
    }

    public function testReviewFormShowsLoginNoticeForGuests(): void
    {
        // Generator defaults to a logged-in customer, so a guest state has to be requested explicitly.
        $guest = new CustomerEntity();
        $guest->setId(Uuid::randomHex());
        $guest->setGuest(true);

        $html = $this->render('Sw:Product:ReviewForm', $this->reviewResult($this->matrix(), new ProductReviewCollection(), 0, 0), $guest);

        // Guests get the login prompt instead of the form.
        static::assertStringContainsString('sw-product-review-form__login', $html);
        static::assertStringNotContainsString('sw-product-review-form__form', $html);
    }

    public function testReviewFormRendersTheFormForLoggedInCustomers(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $html = $this->render('Sw:Product:ReviewForm', $this->reviewResult($this->matrix(), new ProductReviewCollection(), 0, 0), $customer);

        static::assertStringNotContainsString('sw-product-review-form__login', $html);

        // The AJAX form and its fields render for a logged-in customer.
        static::assertStringContainsString('sw-product-review-form__form', $html);
        static::assertStringContainsString('name="points"', $html);
        static::assertStringContainsString('name="title"', $html);
        static::assertStringContainsString('name="content"', $html);

        // The rating renders as the interactive star widget.
        static::assertStringContainsString('sw-form-star-rating', $html);
        static::assertStringContainsString('sw-form-star-rating__star', $html);
        static::assertStringContainsString('data-star-point="5"', $html);

        // Without an existing review the edit teaser is present but hidden (revealed after submitting),
        // and the form is visible right away.
        static::assertMatchesRegularExpression('/js-review-form-teaser[^"]*d-none/', $html);
        static::assertDoesNotMatchRegularExpression('/sw-product-review-form__form[^"]*d-none/', $html);
    }

    public function testReviewFormShowsEditTeaserWhenCustomerHasReviewed(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $result = $this->reviewResult($this->matrix(five: 1), new ProductReviewCollection(), total: 1, totalInCurrentLanguage: 1);
        $result->setCustomerReview($this->review(5.0, 'My review title', 'My existing review content that is long enough.'));

        $html = $this->render('Sw:Product:ReviewForm', $result, $customer);

        // The teaser stands in for the form, which is present but hidden and offers a cancel action.
        static::assertStringContainsString('js-review-form-teaser', $html);
        static::assertStringContainsString('js-review-form-toggle', $html);
        static::assertStringContainsString('js-review-form-cancel', $html);
        static::assertMatchesRegularExpression('/sw-product-review-form__form[^"]*d-none/', $html);

        // The existing review pre-fills the form.
        static::assertStringContainsString('My review title', $html);
        static::assertStringContainsString('name="id"', $html);
    }

    /**
     * The review form is a standalone element placed on its own in the layout, so the Product Reviews element
     * renders the list only and never the form.
     */
    public function testReviewsElementDoesNotRenderTheReviewForm(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setGuest(false);

        $html = $this->render('Sw:Product:Reviews', $this->reviewResult($this->matrix(), new ProductReviewCollection(), 0, 0), $customer);

        static::assertStringNotContainsString('data-component="Sw:Product:ReviewForm"', $html);
        static::assertStringNotContainsString('sw-product-review-form__form', $html);
    }

    public function testReviewsElementRendersEmptyStateWithoutAList(): void
    {
        $html = $this->render('Sw:Product:Reviews', $this->reviewResult($this->matrix(), new ProductReviewCollection(), 0, 0));

        static::assertStringContainsString('sw-product-reviews__empty', $html);
        static::assertStringNotContainsString('sw-product-reviews__list', $html);
    }

    /**
     * Pagination is driven by the total, not the loaded page, so a page beyond the first stays reachable.
     */
    public function testReviewsElementRendersPaginationWhenTheTotalExceedsOnePage(): void
    {
        $html = $this->render('Sw:Product:Reviews', $this->reviewResult(
            $this->matrix(five: 100),
            new ProductReviewCollection([$this->review(5.0, 'Title', 'Content')]),
            total: 100,
            totalInCurrentLanguage: 100,
        ));

        static::assertStringContainsString('sw-product-reviews__pagination', $html);

        // JS-driven page links (data-page); Reviews.js reloads the review fragment from the content route.
        static::assertStringContainsString('data-page="2"', $html);
    }

    /**
     * The data loader degrades an unresolvable product to no result, so the element has to render without a
     * result at all — matching Sw:Media:Image and Sw:Product:Listing.
     */
    public function testReviewsElementRendersWithoutAResultInsteadOfFailing(): void
    {
        $html = $this->render('Sw:Product:Reviews', null);

        static::assertStringContainsString('data-component="Sw:Product:Reviews"', $html);
        static::assertStringContainsString('sw-product-reviews__empty', $html);
    }

    /**
     * The reload URL is generated by Twig path() so it carries the shop base path and sales-channel prefix,
     * rather than being rebuilt from window.location. The content-route path keeps its slash intact.
     */
    public function testReviewsElementEmitsBasePathSafeContentUrl(): void
    {
        $productId = Uuid::randomHex();

        $html = $this->render('Sw:Product:Reviews', $this->reviewResult(
            $this->matrix(five: 1),
            new ProductReviewCollection([$this->review(5.0, 'Title', 'Content')]),
            total: 1,
            totalInCurrentLanguage: 1,
            productId: $productId,
        ));

        static::assertStringContainsString('data-component-options', $html);
        static::assertStringContainsString($productId, $html);
        static::assertStringNotContainsString('%2F', $html);
    }

    public function testSummaryElementRendersRatingAndBreakdown(): void
    {
        $html = $this->render('Sw:Product:ReviewSummary', $this->reviewResult($this->matrix(five: 3, four: 1), new ProductReviewCollection(), total: 4, totalInCurrentLanguage: 4));

        static::assertStringContainsString('sw-product-reviews__summary', $html);
        static::assertStringContainsString('sw-rating-stars', $html);

        // One breakdown row per rating point, always five, driven by the matrix.
        static::assertSame(5, substr_count($html, 'class="sw-product-reviews__breakdown-row"'));
    }

    /**
     * The summary shows the aggregate rating only; the login notice belongs to the Product Reviews element.
     */
    public function testSummaryElementDoesNotRenderTheLoginNotice(): void
    {
        $guest = new CustomerEntity();
        $guest->setId(Uuid::randomHex());
        $guest->setGuest(true);

        $html = $this->render('Sw:Product:ReviewSummary', $this->reviewResult($this->matrix(five: 1), new ProductReviewCollection(), total: 1, totalInCurrentLanguage: 1), $guest);

        static::assertStringContainsString('sw-product-reviews__summary', $html);
        static::assertStringNotContainsString('sw-product-reviews__login', $html);
    }

    /**
     * The filters element controls the list over the event bus, so it must carry the JS hook and reflect the
     * result's current sorting in its select.
     */
    public function testFiltersElementRendersControlsAndReflectsTheSorting(): void
    {
        $html = $this->render('Sw:Product:ReviewFilters', $this->reviewResult(
            $this->matrix(five: 1),
            new ProductReviewCollection([$this->review(5.0, 'Title', 'Content')]),
            total: 1,
            totalInCurrentLanguage: 1,
            sorting: new FieldSorting('points'),
        ));

        static::assertStringContainsString('data-component="Sw:Product:ReviewFilters"', $html);
        static::assertStringContainsString('js-reviews-sort', $html);
        static::assertStringContainsString('js-reviews-language-toggle', $html);
        static::assertMatchesRegularExpression('/<option value="points"[^>]*selected/', $html);
    }

    private function bindings(): AbstractContentSystemBindingSpecificationRegistry
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        return $registry;
    }

    private function render(string $component, ?ProductReviewResult $reviews, ?CustomerEntity $customer = null): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $requestStack = static::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);

        // The components read the `context` global (login state, config scope), which the storefront Twig
        // extension only exposes for an active request carrying a sales-channel context.
        $request = new Request();
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT,
            Generator::generateSalesChannelContext(customer: $customer),
        );
        $requestStack->push($request);

        try {
            return $twig
                ->createTemplate('{{ component(name, { reviews: reviews }) }}')
                ->render(['name' => $component, 'reviews' => $reviews]);
        } finally {
            $requestStack->pop();
        }
    }

    private function matrix(int $five = 0, int $four = 0, int $three = 0, int $two = 0, int $one = 0): RatingMatrix
    {
        return new RatingMatrix([
            new Bucket('5', $five, null),
            new Bucket('4', $four, null),
            new Bucket('3', $three, null),
            new Bucket('2', $two, null),
            new Bucket('1', $one, null),
        ]);
    }

    private function review(float $points, string $title, string $content, ?string $comment = null): ProductReviewEntity
    {
        $review = new ProductReviewEntity();
        $review->setId(Uuid::randomHex());
        $review->setPoints($points);
        $review->setTitle($title);
        $review->setContent($content);
        $review->setComment($comment);
        $review->setCreatedAt(new \DateTimeImmutable('2023-01-15 10:00:00'));

        return $review;
    }

    private function reviewResult(
        RatingMatrix $matrix,
        ProductReviewCollection $entities,
        int $total,
        int $totalInCurrentLanguage,
        ?FieldSorting $sorting = null,
        ?string $productId = null,
    ): ProductReviewResult {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(0);

        if ($sorting !== null) {
            $criteria->addSorting($sorting);
        }

        $source = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            $total,
            $entities,
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );

        return ProductReviewResult::fromSearchResult(
            $source,
            matrix: $matrix,
            productId: $productId ?? Uuid::randomHex(),
            totalReviewsInCurrentLanguage: $totalInCurrentLanguage,
        );
    }
}
