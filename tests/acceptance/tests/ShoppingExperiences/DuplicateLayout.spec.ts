import { test, expect } from '@fixtures/AcceptanceTest';
import type { Page } from '@playwright/test';

// The designer saves the whole layout after a duplicate, which renumbers the positions.
function waitForPageSave(page: Page, layoutId: string) {
    return page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname.endsWith(`/api/cms-page/${layoutId}`) &&
            response.request().method() === 'PATCH',
    );
}

function textSection(id: string, blockId: string, position: number) {
    return {
        id,
        position,
        type: 'default',
        blocks: [
            {
                id: blockId,
                type: 'text',
                position: 0,
                slots: [{ type: 'text', slot: 'content' }],
            },
        ],
    };
}

test(
    'Shop administrator should be able to duplicate a layout from the layout listing.',
    { tag: '@ShoppingExperiences' },
    async ({ ShopAdmin, IdProvider, TestDataService, AdminApiContext, AdminLayoutListing }) => {
        const sectionId = IdProvider.getIdPair().uuid;
        const layout = await TestDataService.createBasicPageLayout('landingpage', {
            name: `00_duplicatelayout_${IdProvider.getIdPair().uuid}`,
            sections: [textSection(sectionId, IdProvider.getIdPair().uuid, 0)],
        });
        let cloneId: string;

        await test.step('Duplicate the layout from its card in the listing.', async () => {
            // With the Elasticsearch admin search, typing into the search bar only fills its dropdown and never filters
            // the listing, so the term goes into the URL. The listing loads twice on open; the second load re-renders the cards.
            let filteredListLoads = 0;
            const secondFilteredListResponse = ShopAdmin.page.waitForResponse(
                (response) =>
                    response.url().endsWith('/api/search/cms-page') &&
                    (response.request().postData() ?? '').includes(layout.name) &&
                    ++filteredListLoads === 2,
            );
            await ShopAdmin.goesTo(`${AdminLayoutListing.url()}?term=${encodeURIComponent(layout.name)}`);
            expect((await secondFilteredListResponse).ok()).toBeTruthy();

            const card = AdminLayoutListing.listingGrid
                .locator('.sw-cms-list-item')
                .filter({ has: ShopAdmin.page.getByText(layout.name, { exact: true }) });
            await card.hover();
            await card.locator('.sw-cms-list-item__options').click();

            const cloneResponse = ShopAdmin.page.waitForResponse(`**/api/_action/clone/cms-page/${layout.id}`);
            await ShopAdmin.page.locator('.sw-cms-list-item__option-duplicate').click();

            const response = await cloneResponse;
            expect(response.ok()).toBeTruthy();
            cloneId = (await response.json()).id;
            TestDataService.addCreatedRecord('cms_page', cloneId);
        });

        await test.step('Verify the copy has its own sections, blocks and slots and is listed.', async () => {
            const response = await AdminApiContext.post('search/cms-page', {
                data: {
                    ids: [cloneId],
                    associations: { sections: { associations: { blocks: { associations: { slots: {} } } } } },
                },
            });
            expect(response.ok()).toBeTruthy();
            const [clone] = (await response.json()).data;

            expect(clone.name.startsWith(`${layout.name} - `)).toBe(true);
            expect(clone.sections).toHaveLength(1);
            expect(clone.sections[0].id).not.toBe(sectionId);
            expect(clone.sections[0].blocks).toHaveLength(1);
            expect(clone.sections[0].blocks[0].slots).toHaveLength(1);

            await ShopAdmin.expects(AdminLayoutListing.listingGrid.getByText(clone.name, { exact: true })).toBeVisible();
        });
    },
);

test(
    'Shop administrator should be able to duplicate a section that is followed by another section.',
    { tag: '@ShoppingExperiences' },
    async ({ ShopAdmin, IdProvider, TestDataService, AdminApiContext }) => {
        const firstSectionId = IdProvider.getIdPair().uuid;
        const lastSectionId = IdProvider.getIdPair().uuid;
        const layout = await TestDataService.createBasicPageLayout('landingpage', {
            name: `00_duplicatesection_${IdProvider.getIdPair().uuid}`,
            sections: [
                textSection(firstSectionId, IdProvider.getIdPair().uuid, 0),
                textSection(lastSectionId, IdProvider.getIdPair().uuid, 1),
            ],
        });
        let cloneId: string;

        await test.step('Duplicate the first section in the layout designer.', async () => {
            await ShopAdmin.goesTo(`#/sw/cms/detail/${layout.id}`);
            await ShopAdmin.page.locator('.sw-cms-section').first().locator('.sw-cms-section__action').click();

            const cloneResponse = ShopAdmin.page.waitForResponse(`**/api/_action/clone/cms-section/${firstSectionId}`);
            const pageSaveResponse = waitForPageSave(ShopAdmin.page, layout.id);
            await ShopAdmin.page.getByRole('button', { name: 'Duplicate' }).click();

            const response = await cloneResponse;
            expect(response.ok()).toBeTruthy();
            cloneId = (await response.json()).id;
            expect((await pageSaveResponse).ok()).toBeTruthy();
        });

        await test.step('Verify the saved layout has the copy between both sections.', async () => {
            const response = await AdminApiContext.post('search/cms-section', {
                data: {
                    filter: [{ type: 'equals', field: 'pageId', value: layout.id }],
                    sort: [{ field: 'position', order: 'ASC' }],
                },
            });
            expect(response.ok()).toBeTruthy();

            expect((await response.json()).data.map(({ id, position }) => [id, position])).toEqual([
                [firstSectionId, 0],
                [cloneId, 1],
                [lastSectionId, 2],
            ]);
        });
    },
);

test(
    'Shop administrator should be able to duplicate a block that is followed by another block.',
    { tag: '@ShoppingExperiences' },
    async ({ ShopAdmin, IdProvider, TestDataService, AdminApiContext }) => {
        const sectionId = IdProvider.getIdPair().uuid;
        const firstBlockId = IdProvider.getIdPair().uuid;
        const lastBlockId = IdProvider.getIdPair().uuid;
        const section = textSection(sectionId, firstBlockId, 0);
        section.blocks.push({ ...section.blocks[0], id: lastBlockId, position: 1 });
        const layout = await TestDataService.createBasicPageLayout('landingpage', {
            name: `00_duplicateblock_${IdProvider.getIdPair().uuid}`,
            sections: [section],
        });
        let cloneId: string;

        await test.step('Duplicate the first block in the layout designer.', async () => {
            await ShopAdmin.goesTo(`#/sw/cms/detail/${layout.id}`);
            const firstBlock = ShopAdmin.page.locator('.sw-cms-block').first();
            await firstBlock.hover();
            await firstBlock.locator('.sw-cms-block__config-overlay').click();

            const cloneResponse = ShopAdmin.page.waitForResponse(`**/api/_action/clone/cms-block/${firstBlockId}`);
            const pageSaveResponse = waitForPageSave(ShopAdmin.page, layout.id);
            await ShopAdmin.page.getByRole('button', { name: 'Duplicate' }).click();

            const response = await cloneResponse;
            expect(response.ok()).toBeTruthy();
            cloneId = (await response.json()).id;
            expect((await pageSaveResponse).ok()).toBeTruthy();
        });

        await test.step('Verify the saved section has the copy between both blocks.', async () => {
            const response = await AdminApiContext.post('search/cms-block', {
                data: {
                    filter: [{ type: 'equals', field: 'sectionId', value: sectionId }],
                    sort: [{ field: 'position', order: 'ASC' }],
                },
            });
            expect(response.ok()).toBeTruthy();

            expect((await response.json()).data.map(({ id, position }) => [id, position])).toEqual([
                [firstBlockId, 0],
                [cloneId, 1],
                [lastBlockId, 2],
            ]);
        });
    },
);
