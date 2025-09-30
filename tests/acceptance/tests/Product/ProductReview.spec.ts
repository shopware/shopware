import { test } from '@fixtures/AcceptanceTest';


test('As a shop customer, I want to see reviews of a product.', {
    tag: ['@Product', '@Reviews'],
}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
          }) => {

    const productWithRating1 = await TestDataService.createBasicProduct();
    await TestDataService.createProductReview(productWithRating1.id, { points: 3 });
    await TestDataService.createProductReview(productWithRating1.id, { points: 4 });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithRating1));
    await StorefrontProductDetail.reviewsTab.click();

    await ShopCustomer.expects(StorefrontProductDetail.productReviewRating).toBeVisible();
    await ShopCustomer.expects(StorefrontProductDetail.productReviewsLink).toHaveText('2 Reviews');
    await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toContainText('2 reviews');
    await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);

});

test('As a shop customer, I want to submit a review, so that I can share my experience with the product', {
    tag: ['@Product', '@Reviews'],
}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    LoginViaReviewsTab,
    Logout,
          }) => {

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();

    await test.step('Navigate to review tab within product detail page.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await StorefrontProductDetail.reviewsTab.click();
    });

    await test.step('Validate the empty state of the reviews tab.', async () => {
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(0);
        await ShopCustomer.expects(StorefrontProductDetail.reviewEmptyListingText).toContainText('No reviews found. Share your insights with others.');
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toHaveText('Leave a review!');
    });

    await test.step('Login for review writing and validate the review form.', async () => {
        await StorefrontProductDetail.reviewTeaserButton.click();
        await ShopCustomer.expects(StorefrontProductDetail.reviewLoginForm).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.forgottenPasswordLink).toBeVisible();
        const loginResponse = await StorefrontProductDetail.page.request.post('account/login');
        await ShopCustomer.attemptsTo(LoginViaReviewsTab(product, customer));
        await ShopCustomer.expects(loginResponse).toBeTruthy();
        await TestDataService.clearCaches();

        // collapse depend on page-level initialization (JS event listeners, aria-expanded, etc.) which don’t re-fire after DOM patching.
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await StorefrontProductDetail.reviewsTab.click();
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
        await StorefrontProductDetail.reviewTeaserButton.click();
        await ShopCustomer.expects(StorefrontProductDetail.reviewForm).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints).toHaveCount(5);

        const starRatingPoints = await StorefrontProductDetail.reviewRatingPoints.count();
        for (let i = 0; i < starRatingPoints; i++) {
            await StorefrontProductDetail.reviewRatingPoints.nth(i).click();
            await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints.nth(i)).toHaveClass('product-detail-review-form-star is-active');
            await ShopCustomer.expects(StorefrontProductDetail.reviewRatingText.nth(starRatingPoints - (i + 1))).not.toHaveClass('d-none');
            await ShopCustomer.expects(StorefrontProductDetail.reviewRatingText.nth(starRatingPoints - (i + 1))).toBeVisible();
            const expectedTexts = ['Unsatisfactory', 'Acceptable', 'Good', 'Very good', 'Excellent'];
            await ShopCustomer.expects(StorefrontProductDetail.reviewRatingText.nth(starRatingPoints - (i + 1))).toHaveText(expectedTexts[i]);
        }
    });

    await test.step('Create a review and validate the submitted review.', async () => {
        await StorefrontProductDetail.reviewRatingPoints.nth(3).click();
        const reviewContent = {
            title: `${product.name} is a great choice`,
            content: `${product.name} has a perfect shape and it is easy to use. I can recommend!`,
        };
        await StorefrontProductDetail.reviewTitleInput.fill(reviewContent.title);
        await StorefrontProductDetail.reviewReviewTextInput.fill(reviewContent.content);
        await StorefrontProductDetail.reviewSubmitButton.click();

        await ShopCustomer.expects(StorefrontProductDetail.reviewSubmitMessage).toBeVisible()
        await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toContainText('1 review');
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(1);
        await ShopCustomer.expects(StorefrontProductDetail.reviewItemRatingPoints).toHaveCount(4);
        await ShopCustomer.expects(StorefrontProductDetail.reviewItemTitle).toHaveText(reviewContent.title);
        await ShopCustomer.expects(StorefrontProductDetail.reviewItemContent).toHaveText(reviewContent.content);

        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toContainText('Edit review');
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toContainText('You have already reviewed this product!');
    });

    await test.step('Logout the customer and validate the submitted review is unpublished.', async () => {
        await ShopCustomer.attemptsTo(Logout());
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await StorefrontProductDetail.reviewsTab.click();

        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toContainText('Write review');
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(0);
        await ShopCustomer.expects(StorefrontProductDetail.reviewEmptyListingText).toContainText('No reviews found. Share your insights with others.');
        await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toContainText('Leave a review!');
    });
});

test('As a shop customer, I want to filter reviews, so that I can find the content of a specific rating', {
    tag: ['@Product', '@Reviews'],
}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
          }) => {

    const productWithRating1 = await TestDataService.createBasicProduct();
    await TestDataService.createProductReview(productWithRating1.id, { points: 1 });
    await TestDataService.createProductReview(productWithRating1.id, { points: 2 });
    await TestDataService.createProductReview(productWithRating1.id, { points: 2 });

    await test.step('Validate the setup and functionality of the filters.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithRating1));
        await StorefrontProductDetail.reviewsTab.click();
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(3);

        let reviewFilterRowOptions = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Excellent');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeDisabled();
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionText).toHaveText('Excellent (0)');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionPercentage).toHaveText('0%');

        reviewFilterRowOptions = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Very good');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeDisabled();
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionText).toHaveText('Very good (0)');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionPercentage).toHaveText('0%');

        reviewFilterRowOptions = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Acceptable');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeEnabled();
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionText).toHaveText('Acceptable (2)');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionPercentage).toHaveText('67%');

        await reviewFilterRowOptions.reviewFilterOptionCheckbox.check();
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);
        await reviewFilterRowOptions.reviewFilterOptionCheckbox.uncheck();

        reviewFilterRowOptions = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Unsatisfactory');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeEnabled();
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionText).toHaveText('Unsatisfactory (1)');
        await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionPercentage).toHaveText('33%');

        await reviewFilterRowOptions.reviewFilterOptionCheckbox.check();
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(1);
        await reviewFilterRowOptions.reviewFilterOptionCheckbox.uncheck();

        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(3);
    });
});
