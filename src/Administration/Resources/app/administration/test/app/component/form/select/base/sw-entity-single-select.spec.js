import { shallowMount, createLocalVue } from '@vue/test-utils';
import EntityCollection from 'src/core/data-new/entity-collection.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-loader';

const fixture = [
    { id: utils.createId(), name: 'first entry' },
    { id: utils.createId(), name: 'second entry' },
    { id: utils.createId(), name: 'third entry' }
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isShopwareContext: true },
        fixture,
        fixture.length,
        null
    );
}

const createEntitySingleSelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-loader': Shopware.Component.build('sw-loader')
        },
        mocks: { $tc: key => key },
        propsData: {
            value: null,
            entity: 'test'
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: (value) => Promise.resolve({ id: value, name: value })
                    };
                }
            }
        }
    };

    return shallowMount(Shopware.Component.build('sw-entity-single-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-entity-single-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntitySingleSelect = createEntitySingleSelect();

        expect(swEntitySingleSelect.vm).toBeTruthy();
    });

    it('should have no reset option when it is not defined', async () => {
        const swEntitySingleSelect = createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test'
            }
        });

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).toBeNull();
    });

    it('should have a reset option when it is defined an the value is null', async () => {
        const swEntitySingleSelect = createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test',
                resetOption: 'reset'
            }
        });

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBeNull();
        expect(singleSelection.name).toEqual('reset');
    });

    it('should have no reset option when it is defined but the value is not null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: 'uuid',
                entity: 'test',
                resetOption: 'reset'
            }
        });

        await swEntitySingleSelect.vm.$nextTick();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toEqual('uuid');
        expect(singleSelection.name).toEqual('uuid');
    });

    it('should have prepend reset option to resultCollection when resetOption is given', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();

        const { resultCollection } = swEntitySingleSelect.vm;

        expect(resultCollection.length).toEqual(getCollection().length + 1);
        expect(resultCollection[0].name).toEqual('reset');
    });

    it('should not show the selected item on first entry', async () => {
        const secondItemId = `${fixture[2].id}`;

        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: secondItemId,
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection()),
                            get: (id) => {
                                if (id === secondItemId) {
                                    return Promise.resolve(fixture[2]);
                                }

                                return Promise.reject();
                            }
                        };
                    }
                }
            }
        });

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-select-option--0').text()).toBe('reset');
        expect(wrapper.find('.sw-select-option--1').text()).toBe('first entry');
        expect(wrapper.find('.sw-select-option--2').text()).toBe('second entry');
        expect(wrapper.find('.sw-select-option--3').text()).toBe('third entry');
    });

    it('should not emit the paginate event when user does not scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const selectResultList = wrapper.find('.sw-select-result-list');
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 150 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBe(undefined);
    });

    it('should emit the paginate event when user scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            propsData: {
                value: '',
                entity: 'test',
                resetOption: 'reset'
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getCollection())
                        };
                    }
                }
            }
        });

        await wrapper.find('input').trigger('click');
        await wrapper.vm.$nextTick();

        const selectResultList = wrapper.find('.sw-select-result-list');
        const listContent = wrapper.find('.sw-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050 });
        Object.defineProperty(listContent.element, 'clientHeight', { value: 250 });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 800 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).not.toBe(undefined);
        expect(selectResultList.emitted('paginate').length).toEqual(1);
        expect(selectResultList.emitted('paginate')[0]).toEqual([]);
    });
});
