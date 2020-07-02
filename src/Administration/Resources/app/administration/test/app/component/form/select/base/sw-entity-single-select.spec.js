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
    { id: utils.createId(), name: 'first entry' }
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
            'sw-icon': '<div></div>',
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
    it('should be a Vue.js component', () => {
        const swEntitySingleSelect = createEntitySingleSelect();

        expect(swEntitySingleSelect.isVueInstance()).toBeTruthy();
    });

    it('should have no reset option when it is not defined', () => {
        const swEntitySingleSelect = createEntitySingleSelect({
            propsData: {
                value: null,
                entity: 'test'
            }
        });

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).toBeNull();
    });

    it('should have a reset option when it is defined an the value is null', () => {
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

    it('should have no reset option when it is defined but the value is not null', () => {
        const swEntitySingleSelect = createEntitySingleSelect({
            propsData: {
                value: 'uuid',
                entity: 'test',
                resetOption: 'reset'
            }
        });

        swEntitySingleSelect.vm.$nextTick(() => {
            const { singleSelection } = swEntitySingleSelect.vm;

            expect(singleSelection).not.toBeNull();
            expect(singleSelection.id).toEqual('uuid');
            expect(singleSelection.name).toEqual('uuid');
        });
    });

    it('should have prepend reset option to resultCollection when resetOption is given', () => {
        const swEntitySingleSelect = createEntitySingleSelect({
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
        swEntitySingleSelect.vm.$nextTick(() => {
            const { resultCollection } = swEntitySingleSelect.vm;

            expect(resultCollection.length).toEqual(getCollection().length + 1);
            expect(resultCollection[0].name).toEqual('reset');
        });
    });
});
