import {test} from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';

test('Shop administrator should be able to create a landing page.', {tag: '@Categories'}, async ({
    ShopAdmin,
    IdProvider,
    TestDataService,
    Categories, AdminApiContext, LandingPageDetail, CreateLandingPage,
}) => {
    const layoutUuid = IdProvider.getIdPair().uuid;
    const layoutName = `test ${layoutUuid}`;
    await test.step('Create a landing page layout via API.', async () => {
        await TestDataService.createBasicLandingPageLayout('landingpage', {
                name: layoutName,
                id: layoutUuid,
                type: 'landingpage',
                sections: [
                    {
                        id: IdProvider.getIdPair().uuid,
                        createdAt: '2022-01-01T00:00:00+00:00',
                        pageId: IdProvider.getIdPair().uuid,
                        position: 0,
                        type: 'full_width',
                        blocks: [
                            {
                                id: IdProvider.getIdPair().uuid,
                                sectionId: IdProvider.getIdPair().uuid,
                                type: 'text',
                                position: 0,
                                slots: [
                                    {
                                        id: IdProvider.getIdPair().uuid,
                                        type: 'text',
                                        blockId: IdProvider.getIdPair().uuid,
                                        slot: 'content',
                                    },
                                ],
                            },
                        ],

                    },
                ],
            },
        );
    });

    await test.step('Create a new landing page and assign layout.', async () => {
        await ShopAdmin.goesTo(Categories.url());
        await ShopAdmin.attemptsTo(CreateLandingPage(layoutName, true));
        await ShopAdmin.expects(LandingPageDetail.layoutAssignmentStatus).toBeVisible();
    });

    await test.step('Cleanup created landing page via API', async () => {
        const url = ShopAdmin.page.url();
        const landingPageId = url.split('/')[url.split('/').length - 2];
        const response = await AdminApiContext.delete(`./landing-page/${landingPageId}`);
        expect(response.status()).toBe(204);
    });
});
