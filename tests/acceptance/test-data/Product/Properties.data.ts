import { test as base } from '@playwright/test';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';

export const PropertiesData = base.extend<FixtureTypes>({
    propertiesData: async ({ adminApiContext }, use) => {

        const propertyGroupColorResponse = await adminApiContext.post('property-group?_response=1', {
            data: {
                name: 'Color',
                description: 'Color',
                displayType: 'color',
                sortingType: 'name',
                options: [{
                    name: 'Blue',
                    colorHexCode: '#2148d6',
                }, {
                    name: 'Red',
                    colorHexCode: '#bf0f2a',
                }, {
                    name: 'Green',
                    colorHexCode: '#12bf0f',
                }],
            },
        });

        const propertyGroupSizeResponse = await adminApiContext.post('property-group?_response=1', {
            data: {
                name: 'Size',
                description: 'Size',
                displayType: 'text',
                sortingType: 'name',
                options: [{
                    name: 'Small',
                }, {
                    name: 'Medium',
                }, {
                    name: 'Large',
                }],
            },
        });

        await expect(propertyGroupColorResponse.ok()).toBeTruthy();
        await expect(propertyGroupSizeResponse.ok()).toBeTruthy();

        const { data: propertyGroupColor } = await propertyGroupColorResponse.json();
        const { data: propertyGroupSize } = await propertyGroupSizeResponse.json();

        await use({
            propertyGroupColor,
            propertyGroupSize,
        });

        const deleteGroupColor = await adminApiContext.delete(`property-group/${propertyGroupColor.id}`);
        const deleteGroupSize = await adminApiContext.delete(`property-group/${propertyGroupSize.id}`);

        await expect(deleteGroupColor.ok()).toBeTruthy();
        await expect(deleteGroupSize.ok()).toBeTruthy();
    },
});
