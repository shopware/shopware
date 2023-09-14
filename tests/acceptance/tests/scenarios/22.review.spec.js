import {test, expect} from '../../fixtures/acceptance-test';

test(`Test visibility of reviews`, async ({ salesChannelProduct, storefrontPage, anonStorefrontPage, adminPage }) => {
    // Write review
    await storefrontPage.getByText(salesChannelProduct.name).click();
    await storefrontPage.getByRole('tab', {name: 'Reviews'}).click();
    await storefrontPage.getByRole('button', {name: 'Write review'}).click();
    await storefrontPage.getByPlaceholder('Enter title...').fill('My test review');
    await storefrontPage.getByPlaceholder('Enter your review...').fill('This review has no meaning whatsoever\n\nlorem ipsum\n\nasdf');
    await storefrontPage.getByRole('button', {name: 'Submit'}).click();

    // verify review is visible
    await expect(storefrontPage.getByText('My test review'))
        .toBeVisible();

    // review should not be visible for anon user
    await anonStorefrontPage.getByText(salesChannelProduct.name).click();
    await anonStorefrontPage.getByRole('tab', {name: 'Reviews'}).click();
    await expect(anonStorefrontPage.getByRole('alert').locator('div').first())
        .toHaveText('No reviews found. Share your insights with others.');

    // Approve review in admin
    await adminPage.goto(`#/sw/product/detail/${salesChannelProduct.id}/reviews`);
    await adminPage.getByRole('link', {name: 'My test review'}).click();
    await adminPage.getByLabel('Visible').check()
    await expect(adminPage.getByLabel('Visible')).toBeChecked();
    await adminPage.getByRole('button', {name: 'Save'}).click();

    // HACK: wait for save
    await expect(adminPage.getByRole('button', {name: 'Save'})).not.toBeVisible();
    await expect(adminPage.getByRole('button', {name: 'Save'})).toBeVisible();

    // reload and Verify review in the anon storefront
    await anonStorefrontPage.reload();
    await anonStorefrontPage.getByRole('tab', {name: 'Reviews'}).click();
    await expect(anonStorefrontPage.getByText('My test review'))
        .toBeVisible();

    // TODO:
    // - Deactivate reviews
    // - reload and verify that the review is no longer visible
});
