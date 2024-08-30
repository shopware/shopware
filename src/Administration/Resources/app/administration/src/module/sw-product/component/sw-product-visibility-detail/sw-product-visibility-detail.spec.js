import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */

const salesChannelFixture = {
    id: '12345',
    translated: {
        name: 'Storefront',
    },
};

const visibilityFixture = {
    id: '12345',
    productId: '12345',
    visibility: 30,
    salesChannelInternal: {
        ...salesChannelFixture,
    },
};

const productFixture = {
    id: '12345',
    visibilities: [visibilityFixture],
};

function createStateMapper(customProduct = {}) {
    if (Shopware.State.list().includes('swProductDetail')) {
        Shopware.State.unregisterModule('swProductDetail');
    }

    const newModule = {
        state: {
            product: {
                ...productFixture,
                ...customProduct,
            },
        },
    };

    Shopware.State.registerModule('swProductDetail', {
        ...{
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                product: productFixture,
            },
        },
        ...newModule,
    });
}

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-visibility-detail', {
            sync: true,
        }),
        {
            global: {
                provide: {

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

describe('src/module/sw-settings-listing/component/sw-product-visibility-detail', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('does not exists in given options');
            },
        });
    });

    it('should change visibility value', async () => {
        createStateMapper();
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-visibility-detail__name').text()).toBe(salesChannelFixture.translated.name);
        const radio = wrapper.find('.sw-product-visibility-detail__link-only input');

        await radio.setChecked();
        await radio.trigger('change');

        expect(visibilityFixture.visibility).toBe(10);
    });

    it('should display name tooltip if name is truncated', async () => {
        const name = 'WayTooLongNameThatWillBeTruncated';

        createStateMapper({
            visibilities: [
                {
                    id: salesChannelFixture.id,
                    salesChannel: {
                        ...salesChannelFixture,
                        translated: {
                            name,
                        },
                    },
                },
            ],
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const nameElement = wrapper.find('.sw-product-visibility-detail__name');

        expect(nameElement.exists()).toBe(true);
        expect(nameElement.text().endsWith('...')).toBe(true);
        expect(nameElement.attributes()['tooltip-mock-message']).toBe(name);
    });
});

