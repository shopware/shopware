/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/service/sales-channel-favorites.service';

const PRODUCT_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const STOREFRONT_SALES_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/user-config',
    status: 200,
    response: {
        data: [],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-detail-base', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-switch-field': true,
                'sw-text-field': true,
                'sw-number-field': true,
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-entity-single-select': true,
                'sw-sales-channel-defaults-select': true,
                'router-link': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-radio-field': true,
                'sw-multi-tag-ip-select': true,
                'sw-select-number-field': true,
                'sw-select-field': true,
                'sw-help-text': true,
                'sw-sales-channel-detail-hreflang': true,
                'sw-sales-channel-detail-domains': true,
                'sw-category-tree-field': true,
                'mt-select': true,
                'sw-alert': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                salesChannelService: {},
                productExportService: {},
                knownIpsService: {
                    getKnownIps: () => Promise.resolve(),
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([]);
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        delete: () => {
                            return Promise.resolve();
                        },
                    }),
                },
            },
        },
        props: {
            salesChannel: {},
            productExport: {},
            customFieldSets: [],
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-base', () => {
    beforeEach(async () => {
        Shopware.State.get('session').currentUser = { id: '8fe88c269c214ea68badf7ebe678ab96' };
        global.repositoryFactoryMock.showError = false;
        global.activeAclRoles = [];
    });

    it('should have the select template field disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const selectField = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.templates.placeholderSelectTemplate"]',
        );

        expect(selectField.attributes().disabled).toBe('true');
    });

    it('should have the select template field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const selectField = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.templates.placeholderSelectTemplate"]',
        );

        expect(selectField.attributes().disabled).toBeUndefined();
    });

    it('should have the name field disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[placeholder="sw-sales-channel.detail.placeholderName"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the name field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[placeholder="sw-sales-channel.detail.placeholderName"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the navigation category id field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-navigation-category-id',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the navigation category id field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-navigation-category-id',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the navigation category depth field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-number-field-stub[label="sw-sales-channel.detail.navigationCategoryDepth"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the navigation category depth field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-number-field-stub[label="sw-sales-channel.detail.navigationCategoryDepth"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the service category id field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-service-category-id',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the service category id field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-service-category-id',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the customer group id field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-service-category-id',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the customer group id field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__select-service-category-id',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales channel defaults select for countries field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="countries"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales channel defaults select for countries field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="countries"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales channel defaults select for languages field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="languages"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales channel defaults select for languages field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="languages"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales channel defaults select for paymentMethods field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="paymentMethods"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales channel defaults select for paymentMethods field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="paymentMethods"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales channel defaults select for shippingMethods field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="shippingMethods"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales channel defaults select for shippingMethods field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="shippingMethods"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales channel defaults select for currencies field disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="currencies"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales channel defaults select for currencies field enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-sales-channel-defaults-select-stub[property-name="currencies"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the radio select field for taxCalculationType disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__tax-calculation',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the radio select field for taxCalculationType enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail__tax-calculation',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales-channel-detail-hreflang component disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: STOREFRONT_SALES_CHANNEL_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-sales-channel-detail-hreflang-stub',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the sales-channel-detail-hreflang component enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: STOREFRONT_SALES_CHANNEL_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-sales-channel-detail-hreflang-stub',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the sales-channel-detail-domains component disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: STOREFRONT_SALES_CHANNEL_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-sales-channel-detail-domains-stub',
        );

        expect(field.attributes()['disable-edit']).toBe('true');
    });

    it('should have the sales-channel-detail-domains component enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: STOREFRONT_SALES_CHANNEL_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-sales-channel-detail-domains-stub',
        );

        expect(field.attributes()['disable-edit']).toBeUndefined();
    });

    it('should have the select field for product export storefront sales channel id disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-storefront',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export storefront sales channel id enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-storefront',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select field for product export sales channel domain id disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomainId: '1a',
                storefrontSalesChannelId: '2b',
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-domain',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export sales channel domain id enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomainId: '1a',
                storefrontSalesChannelId: '2b',
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-domain',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select field for product export currency id disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="currency"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export currency id enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="currency"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select field for product export sales channel domain language id disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="language"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export sales channel domain language id not disabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="language"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export sales channel customer group id disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="customer_group"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export sales channel customer group id not disabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomain: {},
            },
        });

        const field = wrapper.get(
            'sw-entity-single-select-stub[entity="customer_group"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field for product export file name disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderFileName"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field for product export file name enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderFileName"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select field for product export encoding disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderSelectEncoding"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export encoding enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();
        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderSelectEncoding"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select field for product export file format disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderSelectFileFormat"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select field for product export file format enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-select-field-stub[placeholder="sw-sales-channel.detail.productComparison.placeholderSelectFileFormat"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the field for product export includeVariants disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.productComparison.includeVariants"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field for product export includeVariants enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.productComparison.includeVariants"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the select number field for product export interval disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '[label="sw-sales-channel.detail.productComparison.interval"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the select number field for product export interval enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '[label="sw-sales-channel.detail.productComparison.interval"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the switch field for product export generateByCronjob disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.productComparison.generateByCronjob"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the switch field for product export generateByCronjob enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.productComparison.generateByCronjob"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the entity single field for product export productStreamId disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-product-stream',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the entity single field for product export productStreamId enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail__product-comparison-product-stream',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the field for salesChannel accessKey disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {},
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.labelAccessKeyField"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field for salesChannel accessKey not disabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {},
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.labelAccessKeyField"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the button for generate keys disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {},
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail-base__button-generate-keys',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the button for generate keys enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {},
        });

        const field = wrapper.get(
            '.sw-sales-channel-detail-base__button-generate-keys',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the field for productExport accessKey disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.productComparison.accessKey"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field for productExport accessKey not disabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.productComparison.accessKey"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    // eslint-disable-next-line jest/no-identical-title
    it('should have the field for productExport accessKey disabled', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomainId: '1a2b3c',
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.productComparison.accessUrl"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    // eslint-disable-next-line jest/no-identical-title
    it('should have the field for productExport accessKey not disabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            salesChannel: {
                typeId: PRODUCT_COMPARISON_TYPE_ID,
            },
            productExport: {
                salesChannelDomainId: '1a2b3c',
            },
        });

        const field = wrapper.get(
            'sw-text-field-stub[label="sw-sales-channel.detail.productComparison.accessUrl"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the button for generating the keys disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail-base__button-generate-keys',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the button for generating the keys enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            '.sw-sales-channel-detail-base__button-generate-keys',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the switch field for salesChannel active disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.labelInputActive"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the switch field for salesChannel active enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.labelInputActive"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the switch field for salesChannel maintenance disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.labelMaintenanceActive"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the switch field for salesChannel maintenance enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-switch-field-stub[label="sw-sales-channel.detail.labelMaintenanceActive"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have the field multi tag ip select for maintenanceIpAllowlist disabled', async () => {
        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-multi-tag-ip-select-stub[label="sw-sales-channel.detail.ipAddressAllowlist"]',
        );

        expect(field.attributes().disabled).toBe('true');
    });

    it('should have the field multi tag ip select for maintenanceIpAllowlist enabled', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const field = wrapper.get(
            'sw-multi-tag-ip-select-stub[label="sw-sales-channel.detail.ipAddressAllowlist"]',
        );

        expect(field.attributes().disabled).toBeUndefined();
    });

    it('should have currency criteria with sort', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.currencyCriteria;

        expect(criteria.parse()).toEqual(expect.objectContaining({
            sort: expect.arrayContaining([
                { field: 'name', order: 'ASC', naturalSorting: false },
            ]),
        }));
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });

    it('"changeInterval" also updates cronjob config', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.changeInterval(0);

        expect(wrapper.vm.disableGenerateByCronjob).toBe(true);
        expect(wrapper.vm.productExport.generateByCronjob).toBe(false);

        wrapper.vm.changeInterval(10);

        expect(wrapper.vm.disableGenerateByCronjob).toBe(false);
        expect(wrapper.vm.productExport.generateByCronjob).toBe(true);
    });
});
