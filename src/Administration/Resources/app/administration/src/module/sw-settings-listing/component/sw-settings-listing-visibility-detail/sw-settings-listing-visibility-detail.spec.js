import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-base-field';
import swSettingsListingVisibiltyDetail from 'src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail';
import swProductVisibilityDetail from 'src/module/sw-product/component/sw-product-visibility-detail';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/grid/sw-grid-column';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-radio-field';

// Turn off known errors
import { unknownOptionError } from 'src/../test/_helper_/allowedErrors';

Shopware.Component.register('sw-settings-listing-visibility-detail', swSettingsListingVisibiltyDetail);
Shopware.Component.register('sw-product-visibility-detail', swProductVisibilityDetail);

global.allowedErrors = [unknownOptionError];

let config = [];

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('sales_channel', 'sales_channel', {}, null, entities);
}

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-settings-listing-visibility-detail'), {
        propsData: {
            config,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(createEntityCollection([
                            {
                                name: 'Headless',
                                translated: { name: 'Headless' },
                                id: '123',
                            },
                        ]));
                    },
                }),
            },
        },
        stubs: {
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-radio-field': await Shopware.Component.build('sw-radio-field'),
            'sw-field-error': {
                template: '<div></div>',
            },
            'sw-grid': await Shopware.Component.build('sw-grid'),
            'sw-pagination': await Shopware.Component.build('sw-pagination'),
            'sw-grid-row': await Shopware.Component.build('sw-grid-row'),
            'sw-grid-column': await Shopware.Component.build('sw-grid-column'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': {
                template: '<div></div>',
            },
        },
    });
}

describe('src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail', () => {
    it('should set selected option by config data', async () => {
        config = [
            {
                id: '123',
                name: 'Headless',
                visibility: 10,
            },
        ];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('.sw-field__radio-option input');

        expect(options.at(2).element.checked).toBe(true);
    });
});

