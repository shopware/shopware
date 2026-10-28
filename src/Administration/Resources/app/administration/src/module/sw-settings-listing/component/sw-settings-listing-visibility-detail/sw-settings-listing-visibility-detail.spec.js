import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */

// Turn off known errors
import { unknownOptionError } from 'test/_helper_/allowedErrors';

global.allowedErrors = [...global.allowedErrors, unknownOptionError];

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
                            // like the API, only return as many sales channels as the criteria asks for
                            search: (criteria) => {
                                return Promise.resolve(
                                    createEntityCollection(props.salesChannels.slice(0, criteria.limit ?? undefined)),
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

    it('should show the name of every configured sales channel, also beyond the first 25', async () => {
        const salesChannels = Array.from({ length: 30 }, (_, index) => ({
            id: `sales-channel-${index}`,
            name: `Sales channel ${index}`,
        }));

        const wrapper = await createWrapper({
            salesChannels,
            config: salesChannels.map(({ id }) => ({ id, visibility: 30 })),
        });
        await flushPromises();

        const pageButtons = wrapper.findAll('.sw-pagination__list-button');
        await pageButtons[pageButtons.length - 1].trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-product-visibility-detail__name').map((name) => name.text())).toEqual(
            salesChannels.slice(20).map(({ name }) => name),
        );
    });
});
