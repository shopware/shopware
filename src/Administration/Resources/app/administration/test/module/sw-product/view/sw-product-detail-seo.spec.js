import { createLocalVue, shallowMount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-seo';
import 'src/module/sw-settings-seo/component/sw-seo-url';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/structure/sw-sales-channel-switch';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';

const { Component, State } = Shopware;

const classes = {
    cardSeoAdditional: 'sw-seo-url__card-seo-additional',
    inheritanceWrapper: 'sw-inherit-wrapper',
    inheritanceSwitch: 'sw-inheritance-switch',
    notInherited: 'sw-inheritance-switch--is-not-inherited',
    inherited: 'sw-inheritance-switch--is-inherited'
};

const storefrontId = uuid.get('storefront');

const productNotInheritedCategoryDataMock = {
    product: {
        seoUrls: [
            {
                apiAlias: null,
                routeName: 'frontend.detail.page',
                salesChannelId: storefrontId
            }
        ],
        mainCategories: [{
            _isNew: true,
            category: {},
            categoryId: uuid.get('category A'),
            extensions: {},
            salesChannelId: storefrontId
        }],
        categories: [{ id: uuid.get('category A') }]
    },
    parentProduct: {
        id: uuid.get('parentProduct'),
        categories: [{ id: uuid.get('category B') }]
    },
    currentSalesChannelId: storefrontId
};

const productInheritedCategoryDataMock = {
    ...productNotInheritedCategoryDataMock,
    product: {
        ...productNotInheritedCategoryDataMock.product,
        categories: []
    }
};

const salesChannelRepositoryMock = {
    search: () => {
        return Promise.resolve(createEntityCollection([
            {
                name: 'Storefront',
                translated: { name: 'Storefront' },
                id: storefrontId
            },
            {
                name: 'Headless',
                translated: { name: 'Headless' },
                id: uuid.get('headless')
            }
        ]));
    }
};

const seoUrlRepositoryMock = {
    create: () => ({}),
    search: () => Promise.resolve([]),
    route: '/seo_url',
    schema: {
        entity: 'seo_url'
    }
};

const repositoryMockFactory = (entity) => {
    if (entity === 'sales_channel') {
        return salesChannelRepositoryMock;
    }

    if (entity === 'seo_url') {
        return seoUrlRepositoryMock;
    }

    return false;
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('tooltip', {});
    localVue.directive('popover', {});

    return shallowMount(Component.build('sw-product-detail-seo'), {
        localVue,
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity)
            },
            validationService: {}
        },
        stubs: {
            'sw-card': {
                data() {
                    return { currentSalesChannelId: null };
                },
                template: '<div>' +
                                '<slot name="toolbar"></slot>' +
                                '<slot></slot>' +
                          '</div>'
            },
            'sw-product-seo-form': true,
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-seo-url': Component.build('sw-seo-url'),
            'sw-seo-main-category': true,
            'sw-sales-channel-switch': Component.build('sw-sales-channel-switch'),
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-inherit-wrapper': Component.build('sw-inherit-wrapper'),
            'sw-text-field': Component.build('sw-text-field'),
            'sw-contextual-field': Component.build('sw-contextual-field'),
            'sw-block-field': Component.build('sw-block-field'),
            'sw-base-field': Component.build('sw-base-field'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-icon': {
                template: '<div class="sw-icon" @click="$emit(\'click\')"></div>'
            },
            'sw-help-text': true,
            'sw-loader': true,
            'sw-field-error': Shopware.Component.build('sw-field-error')
        }
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/module/sw-product/view/sw-product-detail-seo', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {},
                parentProduct: {}
            },
            getters: {
                isLoading: () => false
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should update product main categories correctly', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            product: {
                mainCategories: []
            }
        });

        await wrapper.vm.onAddMainCategory({
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0'
        });

        expect(wrapper.vm.product.mainCategories).toEqual(expect.arrayContaining([{
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0'
        }]));
    });

    it('should update main category when restore inheritance of Seo Category from variant', async () => {
        const wrapper = createWrapper(['product.editor']);
        await wrapper.setData(productInheritedCategoryDataMock);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.product.mainCategories.length).toEqual(1);

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await wrapper.vm.$nextTick();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);

        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch.isVisible()).toBe(true);

        expect(inheritanceSwitch.classes()).toContain(classes.notInherited);

        await inheritanceSwitch.find('.sw-icon').trigger('click');

        expect(inheritanceSwitch.classes()).toContain(classes.inherited);

        expect(wrapper.vm.product.mainCategories.length).toEqual(0);
    });

    it('should not exist inheritance symbol when variant\'s category did not inherit parent\s category', async () => {
        const wrapper = createWrapper('product.editor');
        await wrapper.setData(productNotInheritedCategoryDataMock);

        expect(wrapper.vm.product.categories.length).toEqual(1);
        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.product.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await wrapper.vm.$nextTick();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);


        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch.exists()).toBe(false);
    });

    it('should exist inheritance symbol when variant\'s Seo Category does not have main category', async () => {
        const wrapper = createWrapper('product.editor');

        await wrapper.setData(productInheritedCategoryDataMock);

        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.parentProduct.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const selectHeadless = salesChannelSwitch.find('.sw-select-option--2');
        expect(selectHeadless.text()).toBe('Headless');
        await selectHeadless.trigger('click');
        await wrapper.vm.$nextTick();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Headless');
        expect(wrapper.vm.currentSalesChannelId).toEqual(uuid.get('headless'));

        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch.isVisible()).toBe(true);

        expect(inheritanceSwitch.classes()).toContain(classes.inherited);
    });

    it('should exist non-inheritance symbol when variant\'s Seo Category have main category', async () => {
        const wrapper = createWrapper(['product.editor']);

        await wrapper.setData(productInheritedCategoryDataMock);

        expect(wrapper.vm.product.mainCategories.length).toEqual(1);
        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.parentProduct.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await wrapper.vm.$nextTick();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);

        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch.isVisible()).toBe(true);

        expect(inheritanceSwitch.classes()).toContain(classes.notInherited);
    });
});
