/**
 * @package buyers-experience
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swPromotionV2Discounts from 'src/module/sw-promotion-v2/view/sw-promotion-v2-discounts';
import swPromotionV2WizardDiscountSelection from 'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-wizard-discount-selection';
import 'src/app/component/wizard/sw-wizard';
import 'src/app/component/wizard/sw-wizard-page';
import 'src/app/component/base/sw-empty-state';

Shopware.Component.register('sw-promotion-v2-discounts', swPromotionV2Discounts);
Shopware.Component.extend('sw-promotion-v2-wizard-discount-selection', 'sw-wizard-page', swPromotionV2WizardDiscountSelection);

const { Component } = Shopware;
let stubs = {};

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    stubs = {
        'sw-card': {
            template: '<div class="sw-card"><slot></slot></div>',
        },
        'sw-empty-state': await Component.build('sw-empty-state'),
        'sw-modal': {
            template: '<div class="sw-modal"><slot></slot></div>',
        },
        'sw-wizard': await Component.build('sw-wizard'),
        'sw-wizard-page': await Component.build('sw-wizard-page'),
        'sw-wizard-dot-navigation': true,
        'sw-promotion-v2-wizard-description': {
            template: '<div class="sw-promotion-v2-wizard-description"><slot></slot></div>',
        },
        'sw-promotion-v2-wizard-discount-selection': await Component.build('sw-promotion-v2-wizard-discount-selection'),
        'sw-promotion-v2-settings-discount-type': true,
        'sw-button': true,
        'sw-button-process': true,
        'sw-icon': true,
        'sw-radio-field': true,
    };
    localVue.filter('asset', ((key) => {
        return key;
    }));

    return shallowMount(await Shopware.Component.build('sw-promotion-v2-discounts'), {
        localVue,
        stubs,
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                },
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([{ id: 'promotionId1' }]),
                }),
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
        },
        mocks: {
            $route: { meta: { $module: { icon: 'default-symbol-content', description: 'Foo bar' } } },
            $sanitize: key => key,
        },
        propsData: {
            promotion: {
                name: 'Test Promotion',
                active: true,
                validFrom: '2020-07-28T12:00:00.000+00:00',
                validUntil: '2020-08-11T12:00:00.000+00:00',
                maxRedemptionsGlobal: 45,
                maxRedemptionsPerCustomer: 12,
                exclusive: false,
                code: null,
                useCodes: true,
                useIndividualCodes: false,
                individualCodePattern: 'code-%d',
                useSetGroups: false,
                customerRestriction: true,
                orderCount: 0,
                ordersPerCustomerCount: null,
                exclusionIds: ['d671d6d3efc74d2a8b977e3be3cd69c7'],
                translated: {
                    name: 'Test Promotion',
                },
                apiAlias: null,
                id: 'promotionId',
                setgroups: [],
                salesChannels: [
                    {
                        promotionId: 'promotionId',
                        salesChannelId: 'salesChannelId',
                        priority: 1,
                        createdAt: '2020-08-17T13:24:52.692+00:00',
                        id: 'promotionSalesChannelId',
                    },
                ],
                discounts: [],
                individualCodes: [],
                personaRules: [],
                personaCustomers: [],
                orderRules: [],
                cartRules: [],
                translations: [],
                hasOrders: false,
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-discounts', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open and close the wizard', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showDiscountModal: true,
        });

        expect(wrapper.vm.showDiscountModal).toBeTruthy();
        expect(wrapper.findComponent({ name: 'sw-wizard' }).exists()).toBeTruthy();

        await wrapper.setData({
            showDiscountModal: false,
        });

        expect(wrapper.vm.showDiscountModal).toBeFalsy();
        expect(wrapper.findComponent({ name: 'sw-wizard' }).exists()).toBeFalsy();
    });

    it('should disable adding discounts when privileges not set', async () => {
        const wrapper = await createWrapper();

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeTruthy();
    });

    it('should enable adding discounts when privilege is set', async () => {
        const wrapper = await createWrapper([
            'promotion.editor',
        ]);

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeFalsy();
    });
});
