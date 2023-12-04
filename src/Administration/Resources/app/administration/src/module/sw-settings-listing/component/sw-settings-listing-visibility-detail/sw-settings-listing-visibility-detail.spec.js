import { mount } from '@vue/test-utils';

// Turn off known errors
import { unknownOptionError } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors = [unknownOptionError];

let config = [];

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('sales_channel', 'sales_channel', {}, null, entities);
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-listing-visibility-detail', {
        sync: true,
    }), {
        props: {
            config,
        },
        global: {
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
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-grid': await wrapTestComponent('sw-grid'),
                'sw-pagination': await wrapTestComponent('sw-pagination'),
                'sw-grid-row': await wrapTestComponent('sw-grid-row'),
                'sw-grid-column': await wrapTestComponent('sw-grid-column'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-icon': {
                    template: '<div></div>',
                },
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
        await flushPromises();

        const options = wrapper.findAll('.sw-field__radio-option input');

        expect(options[2].element.checked).toBe(true);
    });
});

