import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to submit a review, so that I can share my experience with the product', {
    tag: ['@Product'],
}, async ({
ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
          }) => {

    const product = await TestDataService.createBasicProduct();
    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await StorefrontProductDetail.reviewsTab.click();

    //Validation
    await ShopCustomer.expects(StorefrontProductDetail.writeReviewButton).toBeVisible();
    await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(0);
    await ShopCustomer.expects(StorefrontProductDetail.reviewEmptyListingText).toContainText('No reviews found. Share your insights with others.');

    //Login as customer
    await TestDataService.createCustomer();
    await StorefrontProductDetail.writeReviewButton.click();

    await ShopCustomer.expects(StorefrontProductDetail.showReviewsButton).toBeVisible();
    await ShopCustomer.expects(StorefrontProductDetail.reviewLoginForm).toBeVisible();
    await ShopCustomer.expects(StorefrontProductDetail.forgottenPassswordLink).toBeVisible();

    await ShopCustomer.attemptsTo(LoginViaReviewTab(email));
    await StorefrontProductDetail.reviewsTab.click();
    await StorefrontProductDetail.writeReviewButton.click();

    //Validation
    await ShopCustomer.expects(StorefrontProductDetail.reviewForm).toBeVisible();
    await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints).toBeVisible();

    //Validation of star rating
    await StorefrontProductDetail.reviewRatingPoints.first().click();
    await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints.first()).toHaveClass('is-active');
    await ShopCustomer.expects(StorefrontProductDetail.reviewRatingTextFiveStars).not.toHaveClass('d-none');


    //Submit review
    await ShopCustomer.attemptsTo(SubmitReview);

    //Validation
    await ShopCustomer.expects(StorefrontProductDetail.reviewSubmitMessage).toContainText('Thank you for your review! It will be published once it has been approved by a moderator.');
    await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toContainText('1 review');
    await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(1);
    await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints.first()).toHaveClass('point-full');

});
