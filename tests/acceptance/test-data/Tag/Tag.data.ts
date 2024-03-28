import { test as base } from 'playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { expect } from '@fixtures/AcceptanceTest';
import { components } from '@shopware/api-client/admin-api-types';

export const TagData = base.extend<FixtureTypes>({
    tagData: async ({ idProvider, adminApiContext }, use) => {

        // Generate tag
        const tagUUID = idProvider.getIdPair().uuid;
        const tagId = idProvider.getIdPair().id
        const tagName = `Test-${tagId}`;

        // Create tag
        const tagResponse = await adminApiContext.post('./tag?_response=detail', {
            data: {
                id: tagUUID,
                name: tagName,
            },
        });

        await expect(tagResponse.ok()).toBeTruthy();
        const { data: tag } = (await tagResponse.json()) as { data: components['schemas']['Tag'] };

        // Use tag data in the test
        await use(tag);

        // Delete tag after the test is done
        const cleanupResponse = await adminApiContext.delete(`./tag/${tag.id}`);
        await expect(cleanupResponse.ok()).toBeTruthy();
    },
});
