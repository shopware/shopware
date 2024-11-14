import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */

// Turn off known errors
import { unknownOptionError } from 'src/../test/_helper_/allowedErrors';

global.allowedErrors = [
    ...global.allowedErrors,
    unknownOptionError,
];

const defaultSalesChannel = {
    name: 'Headless',
    translated: { name: 'Headless' },
    id: '123',
};

const defaultProps = {
    config: [],
    salesChannels: [defaultSalesChannel],
};

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('sales_channel', 'sales_channel', {}, null, entities);
}

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('sw-settings-listing-visibility-detail', {
            sync: true,
        }),
        {
            props: {
                config: props.config,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(
                                    createEntityCollection([
                                        ...props.salesChannels,
                                    ]),
                                );
                            },
                        }),
                    },
                },
                stubs: {
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                    'sw-grid': await wrapTestComponent('sw-grid'),
                    'sw-pagination': await wrapTestComponent('sw-pagination'),
                    'sw-grid-row': await wrapTestComponent('sw-grid-row'),
                    'sw-grid-column': await wrapTestComponent('sw-grid-column'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-icon': {
                        template: '<div></div>',
                    },
                    'sw-field-error': {
                        template: '<div></div>',
                    },
                    'sw-checkbox-field': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-select-field': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-listing/component/sw-settings-listing-visibility-detail', () => {
    it('should set selected option by config data', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            config: [
                ...defaultProps.config,
                {
                    id: '123',
                    name: 'Headless',
                    visibility: 10,
                },
            ],
        });
        await flushPromises();

        const options = wrapper.findAll('.sw-field__radio-option input');

        expect(options[2].element.checked).toBe(true);
    });

    it('should display name tooltip if name is truncated', async () => {
        const name = 'WayTooLongNameThatWillBeTruncated';

        const wrapper = await createWrapper({
            ...defaultProps,
            salesChannels: [
                {
                    ...defaultSalesChannel,
                    name,
                },
            ],
            config: [
                ...defaultProps.config,
                {
                    id: '123',
                    name,
                    visibility: 10,
                },
            ],
        });
        await flushPromises();

        const nameElement = wrapper.find('.sw-product-visibility-detail__name');

        expect(nameElement.exists()).toBe(true);
        expect(nameElement.text().endsWith('...')).toBe(true);
        expect(nameElement.attributes()['tooltip-mock-message']).toBe(name);
    });
});
