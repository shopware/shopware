import { shallowMount, createLocalVue } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/base/sw-label';
import 'src/app/component/form/select/entity/sw-entity-many-to-many-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-product-variant-info';

const { Criteria } = Shopware.Data;

const fixture = [
    { id: utils.createId(), name: 'first entry', variation: [{ group: 'Size', option: 'M' }] },
    { id: utils.createId(), name: 'second entry' },
    { id: utils.createId(), name: 'third entry' }
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        { isShopwareContext: true },
        new Criteria(),
        fixture,
        fixture.length,
        null
    );
}

const criteria = new Criteria();
criteria.addSorting(Criteria.sort('entity.name', 'DESC'));
criteria.addFilter(Criteria.equals('entity.key', 'value'));

const createEntityManyToManySelect = (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div @click="$emit(\'click\', $event)"></div>'
            },
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-loader': Shopware.Component.build('sw-loader'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-label': Shopware.Component.build('sw-label'),
        },
        propsData: {
            entityCollection: getCollection(),
            criteria: criteria,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: jest.fn(() => Promise.resolve([])),
                })
            }
        }
    };

    return shallowMount(Shopware.Component.build('sw-entity-many-to-many-select'), {
        ...options,
        ...customOptions
    });
};

describe('components/sw-entity-many-to-many-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntityManyToManySelect = createEntityManyToManySelect();

        expect(swEntityManyToManySelect.vm).toBeTruthy();
    });

    it('should use the given criteria to search the repository', async () => {
        const swEntityManyToManySelect = createEntityManyToManySelect();

        swEntityManyToManySelect.vm.sendSearchRequest();

        expect(swEntityManyToManySelect.vm.searchRepository.search).toBeCalledWith(criteria, Shopware.Context.api);
    });
});
