import { test } from '@fixtures/AcceptanceTest';

test('As a merchant, I want to make sure admin events are sent correctly.', { tag: '@ProductAnalytics' }, async ({
    ShopAdmin,
    FeatureService,
    AdminDashboard,
    AdminManufacturerListing,
    }) => {

    test.skip(!(await FeatureService.isEnabled('PRODUCT_ANALYTICS')), 'Product Analytics feature flag is not enabled.');

    // Mock the api call before navigating
    await AdminManufacturerListing.page.route(`https://api.eu.amplitude.com/2/httpapi`, async route => {

        console.log(route.request().postData())
        //const json = [{ name: 'My custom Mock Event' }];
        console.log('Mocking Amplitude API call');
        //await route.fulfill({ json });
        const response = await route.fetch();
        // Add a prefix to the title.
        console.log(await response.text());

        //await route.abort();
        // await route.fulfill({
        //     status: 404,
        //     contentType: 'text/plain',
        //     body: 'Not Found!',
        // });
        await route.continue();
    });

    await ShopAdmin.goesTo(AdminManufacturerListing.url());

});
