import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop customer, I want to see reviews of a product.',
    {
        tag: ['@Product', '@Reviews', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail }) => {
        const productWithRating1 = await TestDataService.createBasicProduct();
        await TestDataService.createProductReview(productWithRating1.id, { points: 3 });
        await TestDataService.createProductReview(productWithRating1.id, { points: 4 });

        await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithRating1));
        await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);

        await ShopCustomer.expects(StorefrontProductDetail.productReviewRating).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.productReviewsLink).toHaveText('2 Reviews');
        await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toContainText('2 reviews');
        await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);
    },
);

test(
    'As a shop customer, I want to submit a review, so that I can share my experience with the product',
    {
        tag: ['@Product', '@Reviews', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, LoginViaReviewsTab, Logout, InstanceMeta }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();

        await test.step('Navigate to review tab within product detail page.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);
        });

        await test.step('Validate the empty state of the reviews tab.', async () => {
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(0);
            await ShopCustomer.expects(StorefrontProductDetail.reviewEmptyListingText).toContainText(
                'No reviews found. Share your insights with others.',
            );
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toHaveText('Leave a review!');
        });

        await test.step('Login for review writing and validate the review form.', async () => {
            await ShopCustomer.presses(StorefrontProductDetail.reviewTeaserButton);
            await ShopCustomer.expects(StorefrontProductDetail.reviewLoginForm).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.forgottenPasswordLink).toBeVisible();
            await ShopCustomer.attemptsTo(LoginViaReviewsTab(product, customer));
            if (InstanceMeta.isSaaS || InstanceMeta.isPaaS) {
                await TestDataService.clearCaches();
            }

            // collapse depend on page-level initialization (JS event listeners, aria-expanded, etc.) which don’t re-fire after DOM patching.
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.presses(StorefrontProductDetail.reviewTeaserButton);
            await ShopCustomer.expects(StorefrontProductDetail.reviewForm).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.reviewRatingPoints).toHaveCount(5);

            const starRatingPoints = await StorefrontProductDetail.reviewRatingPoints.count();
            for (let i = 0; i < starRatingPoints; i++) {
                const expectedTexts = ['Unsatisfactory', 'Acceptable', 'Good', 'Very good', 'Excellent'];
                await ShopCustomer.selectsRadioButton(StorefrontProductDetail.reviewRatingPoints, expectedTexts[i], true);
                await ShopCustomer.expects(async () => {
                    await ShopCustomer.expects(
                        StorefrontProductDetail.reviewRatingText.nth(starRatingPoints - (i + 1)),
                    ).toHaveText(expectedTexts[i]);
                }).toPass();
            }
        });

        await test.step('Create a review and validate the submitted review.', async () => {
            await ShopCustomer.selectsRadioButton(StorefrontProductDetail.reviewRatingPoints, 'Very good');
            const reviewContent = {
                title: `${product.name} is a great choice`,
                content: `${product.name} has a perfect shape and it is easy to use. I can recommend!`,
            };
            await ShopCustomer.fillsIn(StorefrontProductDetail.reviewTitleInput, reviewContent.title);
            await ShopCustomer.fillsIn(StorefrontProductDetail.reviewReviewTextInput, reviewContent.content);
            await ShopCustomer.presses(StorefrontProductDetail.reviewSubmitButton);

            const submitButtonLoadingIcon = StorefrontProductDetail.reviewSubmitButton.locator('.loader');
            await ShopCustomer.expects(submitButtonLoadingIcon).toBeVisible();
            await submitButtonLoadingIcon.waitFor({ state: 'hidden' });

            await ShopCustomer.expects(StorefrontProductDetail.reviewSubmitMessage).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toContainText('1 review');
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(1);
            await ShopCustomer.expects(StorefrontProductDetail.reviewItemRatingPoints).toHaveCount(4);
            await ShopCustomer.expects(StorefrontProductDetail.reviewItemTitle).toHaveText(reviewContent.title);
            await ShopCustomer.expects(StorefrontProductDetail.reviewItemContent).toHaveText(reviewContent.content);

            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toContainText('Edit review');
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toContainText(
                'You have already reviewed this product!',
            );
        });

        await test.step('Logout the customer and validate the submitted review is unpublished.', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);

            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserButton).toContainText('Write review');
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(0);
            await ShopCustomer.expects(StorefrontProductDetail.reviewEmptyListingText).toContainText(
                'No reviews found. Share your insights with others.',
            );
            await ShopCustomer.expects(StorefrontProductDetail.reviewTeaserText).toContainText('Leave a review!');
        });
    },
);

test(
    'As a shop customer, I want to filter reviews, so that I can find the content of a specific rating',
    {
        tag: ['@Product', '@Reviews', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, SelectProductReviewOption }) => {
        const productWithRating1 = await TestDataService.createBasicProduct();
        await TestDataService.createProductReview(productWithRating1.id, { points: 1 });
        await TestDataService.createProductReview(productWithRating1.id, { points: 2 });
        await TestDataService.createProductReview(productWithRating1.id, { points: 2 });

        await test.step('Validate the setup of the filters.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(productWithRating1));
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(3);

            const reviewFilterExcellent = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Excellent');
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionCheckbox).toBeDisabled();
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionText).toHaveText('Excellent (0)');
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionPercentage).toHaveText('0%');

            const reviewFilterVeryGood = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Very good');
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionCheckbox).toBeDisabled();
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionText).toHaveText('Very good (0)');
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionPercentage).toHaveText('0%');

            const reviewFilterAcceptable = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Acceptable');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionText).toHaveText('Acceptable (2)');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionPercentage).toHaveText('67%');

            const reviewFilterUnsatisfactory =
                await StorefrontProductDetail.getReviewFilterRowOptionsByName('Unsatisfactory');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionText).toHaveText('Unsatisfactory (1)');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionPercentage).toHaveText('33%');
        });

        await test.step('Validate the functionality of the filters.', async () => {
            const reviewFilterAcceptable = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Acceptable');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).not.toBeChecked();
            await ShopCustomer.attemptsTo(SelectProductReviewOption('Acceptable'));
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);

            await ShopCustomer.attemptsTo(SelectProductReviewOption('Acceptable'));
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).not.toBeChecked();

            const reviewFilterUnsatisfactory =
                await StorefrontProductDetail.getReviewFilterRowOptionsByName('Unsatisfactory');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).not.toBeChecked();
            await ShopCustomer.attemptsTo(SelectProductReviewOption('Unsatisfactory'));
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(1);

            await ShopCustomer.attemptsTo(SelectProductReviewOption('Unsatisfactory'));
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).not.toBeChecked();

            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(3);
        });
    },
);

test(
    'As a shop customer, I want to filter reviews by rating, log in and come back to the product detail page.',
    {
        tag: ['@Product', '@Reviews', '@Storefront'],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, LoginViaReviewsTab }) => {
        const customer = await TestDataService.createCustomer();
        const product = await TestDataService.createBasicProduct();

        await TestDataService.createProductReview(product.id, { points: 5 });
        await TestDataService.createProductReview(product.id, { points: 5 });
        await TestDataService.createProductReview(product.id, { points: 3 });
        await TestDataService.createProductReview(product.id, { points: 2 });
        await TestDataService.createProductReview(product.id, { points: 1 });

        await test.step('Navigate to review tab within product detail page.', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(5);
        });

        await test.step('Filter down the reviews of the product by rating', async () => {
            const reviewFilterRowOptions = await StorefrontProductDetail.getReviewFilterRowOptionsByName('Excellent (2)');
            await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.presses(reviewFilterRowOptions.reviewFilterOptionCheckbox);
            await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);
        });

        await test.step('Log in and comes back to the product detail page', async () => {
            await ShopCustomer.attemptsTo(LoginViaReviewsTab(product, customer));
            await ShopCustomer.presses(StorefrontProductDetail.reviewsTab);
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(5);

            await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1')).toContainText(product.name);
        });
    },
);
