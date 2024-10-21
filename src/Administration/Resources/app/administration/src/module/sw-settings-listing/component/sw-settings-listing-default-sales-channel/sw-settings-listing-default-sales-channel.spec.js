import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */
describe('src/module/sw-settings-listing/component/sw-settings-listing-default-sales-channel', () => {
    let defaultSalesChannelData = {};

    function createEntityCollection(entities = []) {
        return new Shopware.Data.EntityCollection('sales_channel', 'sales_channel', {}, null, entities);
    }

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-settings-listing-default-sales-channel', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        repositoryFactory: {
                            create: () => ({
                                search: () => {
                                    return Promise.resolve(
                                        createEntityCollection([
                                            {
                                                name: 'Storefront',
                                                translated: {
                                                    name: 'Storefront',
                                                },
                                                id: 'STORE-FRONT-MOCK-ID',
                                            },
                                            {
                                                name: 'Headless',
                                                translated: {
                                                    name: 'Headless',
                                                },
                                                id: 'HEADLESS-MOCK-ID',
                                            },
                                        ]),
                                    );
                                },
                            }),
                        },
                        systemConfigApiService: {
                            getValues: () => Promise.resolve(defaultSalesChannelData),
                        },
                    },
                    stubs: {
                        'sw-base-field': await wrapTestComponent('sw-base-field'),
                        'sw-block-field': await wrapTestComponent('sw-block-field'),
                        'sw-entity-multi-id-select': await wrapTestComponent('sw-entity-multi-id-select'),
                        'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                        'sw-field-error': true,
                        'sw-highlight-text': true,
                        'sw-icon': true,
                        'sw-label': true,
                        'sw-loader': true,
                        'sw-modal': true,
                        'sw-popover': {
                            props: ['popoverClass'],
                            template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                        },
                        'sw-select-base': await wrapTestComponent('sw-select-base'),
                        'sw-select-result': await wrapTestComponent('sw-select-result'),
                        'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                        'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                        'sw-settings-listing-visibility-detail': true,
                        'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                        'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                        'sw-button': true,
                        'sw-ai-copilot-badge': true,
                        'sw-help-text': true,
                        'sw-product-variant-info': true,
                        'sw-inheritance-switch': true,
                    },
                },
            },
        );
    }

    let wrapper;
    const selectors = {
        componentScope: '.sw-settings-listing-default-sales-channel',
        quickLink: '.sw-settings-listing-default-sales-channel__quick-link',
        activeSwitch: '.sw-settings-listing-default-sales-channel__active-switch input',
        visibilityModal: '.sw-settings-listing-default-sales-channel__visibility-modal',
        selectSelection: '.sw-select__selection',
        resultListItems: '.sw-select-result-list__item-list',
    };

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render data correctly at Default Sales Channel card when there is no default sales channel data', async () => {
        const setVisibilityButton = wrapper.find(selectors.quickLink);
        const activeSwitch = wrapper.find(selectors.activeSwitch);

        expect(activeSwitch.element.checked).toBe(true);
        expect(setVisibilityButton.exists()).toBeFalsy();
    });

    it('should render data correctly at Default Sales Channel card when there is default sales channel data', async () => {
        defaultSalesChannelData = {
            'core.defaultSalesChannel.active': false,
            'core.defaultSalesChannel.salesChannel': [
                {
                    id: '98432def39fc4624b33213a56b8c944d',
                    name: 'Headless',
                },
            ],
            'core.defaultSalesChannel.visibility': {
                '98432def39fc4624b33213a56b8c944d': 10,
            },
        };

        wrapper = await createWrapper();
        await flushPromises();

        const visibilityModalButton = wrapper.find(selectors.quickLink);
        const activeSwitch = wrapper.find(selectors.activeSwitch);

        expect(activeSwitch.element.checked).toBe(false);
        expect(visibilityModalButton.exists()).toBeTruthy();
    });

    it('should display "Set visibility for selected Sales Channels" button when a sales channel is selected', async () => {
        const salesChannelCard = wrapper.find(selectors.componentScope);

        await salesChannelCard.find(selectors.selectSelection).trigger('click');

        await salesChannelCard.find('input').trigger('change');
        await flushPromises();

        const list = wrapper.find(selectors.resultListItems).findAll('li');

        await list.at(0).trigger('click');
        await flushPromises();

        expect(salesChannelCard.find(selectors.quickLink).exists()).toBeTruthy();
    });

    it('should display modal when clicking "Set visibility for selected Sales Channels" button', async () => {
        const salesChannelCard = wrapper.find(selectors.componentScope);

        await salesChannelCard.find(selectors.selectSelection).trigger('click');

        await salesChannelCard.find('input').trigger('change');
        await flushPromises();

        const list = wrapper.find(selectors.resultListItems).findAll('li');

        await list.at(0).trigger('click');
        await flushPromises();

        await salesChannelCard.find(selectors.quickLink).trigger('click');
        await flushPromises();

        expect(wrapper.find(selectors.visibilityModal).exists()).toBeTruthy();
    });
});
