import { createLocalVue, shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/sw-promotion/component/sw-promotion-individualcodes';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-button';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-individualcodes'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-number-field': {
                template: '<div class="sw-number-field"><slot></slot></div>'
            },
            'sw-help-text': true,
            'sw-field-error': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-progress-bar': {
                template: '<div class="sw-progress-bar"><slot></slot></div>'
            },
            'sw-label': {
                template: '<div class="sw-label"><slot></slot></div>'
            },
            'sw-empty-state': {
                template: '<div class="sw-empty-state"><slot></slot></div>'
            },
            'sw-data-grid': {
                template: '<div class="sw-data-grid"><slot></slot><slot name="actions"></slot></div>'
            },
            'sw-icon': {
                template: '<div class="sw-icon"></div>'
            }
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: () => {
                    return { search: () => {
                        const codeCollection = new EntityCollection(null, null, null, null, [
                            { id: 'codeId', isRedeemed: false, payload: null, orderId: null }
                        ]);
                        return Promise.resolve(codeCollection);
                    } };
                }
            },
            validationService: () => {}
        },
        mocks: {
            $tc: v => v
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
                useIndividualCodes: true,
                individualCodePattern: 'code-%d',
                useSetGroups: false,
                customerRestriction: true,
                orderCount: 0,
                ordersPerCustomerCount: null,
                exclusionIds: ['d671d6d3efc74d2a8b977e3be3cd69c7'],
                translated: {
                    name: 'Test Promotion'
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
                        id: 'promotionSalesChannelId'
                    }
                ],
                discounts: [],
                individualCodes: [],
                personaRules: [],
                personaCustomers: [],
                orderRules: [],
                cartRules: [],
                translations: [],
                hasOrders: false
            }
        }
    });
}

describe('src/module/sw-promotion/component/sw-promotion-individualcodes', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve([{}]);
                    }
                },
                getBasicHeaders() {
                    return {};
                }
            };
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled form fields', async () => {
        expect(wrapper.vm.isEditingDisabled).toBe(true);

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.find('input').attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-number-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-button');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.find('input').attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-number-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-button');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });

    it('should be individualCodePattern can be generate', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);
        wrapper.vm.createNotificationWarning = jest.fn();
        wrapper.setProps({
            promotion: {
                ...wrapper.props().promotion,
                individualCodePattern: null
            }
        });

        expect(wrapper.vm.shouldStartGenerate).toEqual(false);

        const individualCodePattern = wrapper.findAll('.sw-field').at(0).find('input');
        await individualCodePattern.setValue('code-%d-%s');
        expect(wrapper.vm.promotion.individualCodePattern).toBe('code-%d-%s');

        await wrapper.find('.sw-button').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.shouldStartGenerate).toEqual(true);

        expect(wrapper.vm.createNotificationWarning).not.toHaveBeenCalled();
        ['warningFormatCodes', 'warningNotAllowPattern'].forEach(warning => {
            expect(wrapper.vm.createNotificationWarning).not.toHaveBeenCalledWith({
                message: `sw-promotion.detail.main.general.codes.individual.alerts.${warning}`
            });
        });
    });

    it('should throw an warning message when the individualCodePattern invalid couldn\'t be generate', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        const individualCodePattern = wrapper.findAll('.sw-field').at(0).find('input');
        await individualCodePattern.setValue('my-code');
        expect(wrapper.vm.promotion.individualCodePattern).toBe('my-code');

        await wrapper.find('.sw-button').trigger('click');

        expect(wrapper.vm.shouldStartGenerate).toEqual(false);
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-promotion.detail.main.general.codes.individual.alerts.warningFormatCodes'
        });
    });

    it('should throw an warning message not allow to generate number', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        wrapper.setData({ generateCount: 11 });

        const individualCodePattern = wrapper.findAll('.sw-field').at(0).find('input');
        await individualCodePattern.setValue('code-%d');
        expect(wrapper.vm.promotion.individualCodePattern).toBe('code-%d');

        await wrapper.find('.sw-button').trigger('click');

        expect(wrapper.vm.shouldStartGenerate).toEqual(false);
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-promotion.detail.main.general.codes.individual.alerts.warningNotAllowPattern'
        });
    });

    it('should throw an warning message not allow to generate characters', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        wrapper.setData({ generateCount: 27 });

        const individualCodePattern = wrapper.findAll('.sw-field').at(0).find('input');
        await individualCodePattern.setValue('code-%s');
        expect(wrapper.vm.promotion.individualCodePattern).toBe('code-%s');

        await wrapper.find('.sw-button').trigger('click');

        expect(wrapper.vm.shouldStartGenerate).toEqual(false);
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-promotion.detail.main.general.codes.individual.alerts.warningNotAllowPattern'
        });
    });
});
