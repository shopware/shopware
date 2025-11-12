// Annotate entire file as serial.
import { test } from '@fixtures/AcceptanceTest';
import { expect, Request, Route, Page } from '@playwright/test';
import {
    createNewAdminPageContext,
    getLocale,
    LanguageHelper,
    loginToAdministration,
    setCurrentContext, User,
} from '@shopware-ag/acceptance-test-suite';

interface CapturedRequest {
    postData: string;
}

/**
 * Settings for running tests in serial mode.
 * This is important for tests that modify global state or settings.
 */
test.describe.configure({ mode: 'serial' });

/**
 * Endpoint for Product Analytics API.
 */
const PRODUCT_ANALYTICS_ENDPOINT = 'httpapi';


test('As a merchant, I want to make sure no admin events are sent after login if no consent is given.', { tag: '@ProductAnalytics' }, async ({
    CustomTranslationResources,
    IdProvider,
    SalesChannelBaseConfig,
    AdminApiContext,
    browser,
}) => {

    const captured: CapturedRequest[] = [];
    let page: Page;
    let languageHelper: LanguageHelper;
    let adminUser: User;

    const requestHandler = async (route: Route) => {
        const req: Request = route.request();
        captured.push({
            postData: req.postData(),
        });
        await route.fulfill(
            {
                status: 200,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'code': 200,
                }),
            }
        )
    };

    await test.step('Setup page object before login to shopware administration', async () => {
        const locale = getLocale();
        languageHelper = await LanguageHelper.createInstance(locale, CustomTranslationResources);

        const { id, uuid } = IdProvider.getIdPair();

        adminUser = {
            id: uuid,
            username: `admin_${id}`,
            firstName: `${id} admin`,
            lastName: `${id} admin`,
            localeId: SalesChannelBaseConfig.currentLocaleId,
            email: `admin_${id}@example.com`,
            timezone: 'Europe/Berlin',
            password: 'shopware',
            admin: true,
        };

        const response = await AdminApiContext.post('user', {
            data: adminUser,
        });

        expect(response.ok()).toBeTruthy();

        page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    });

    await test.step('Intercept all the API calls to product analytics', async () => {
        await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}`, requestHandler);
    });

    await test.step('Login to shopware administration', async () => {
        page = await loginToAdministration(
            page,
            adminUser,
            AdminApiContext,
        );

        LanguageHelper.setForContext(page.context() as unknown as Record<string, unknown>, languageHelper);
        setCurrentContext(page.context() as unknown as Record<string, unknown>);
    });

    await test.step('Validate no captured requests for product analytics', async () => {

        expect(captured.length).toBe(0);
    });
});


test('As a merchant, I want explicitly decline both consents from modal.', { tag: '@ProductAnalytics' }, async ({

}) => {
    // This is a placeholder for the test implementation.
});


