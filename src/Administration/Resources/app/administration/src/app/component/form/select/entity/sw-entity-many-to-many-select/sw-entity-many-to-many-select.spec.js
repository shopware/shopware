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
    { id: utils.createId(), name: 'third entry' },
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        { isShopwareContext: true },
        new Criteria(1, 25),
        fixture,
        fixture.length,
        null,
    );
}

const criteria = new Criteria(1, 25);
criteria.addSorting(Criteria.sort('entity.name', 'DESC'));
criteria.addFilter(Criteria.equals('entity.key', 'value'));

const deleteFn = jest.fn(() => Promise.resolve());
const assignFn = jest.fn(() => Promise.resolve());

const createEntityManyToManySelect = async (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div @click="$emit(\'click\', $event)"></div>',
            },
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-label': await Shopware.Component.build('sw-label'),
        },
        propsData: {
            entityCollection: getCollection(),
            criteria: criteria,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: jest.fn(() => Promise.resolve([])),
                    delete: deleteFn,
                    assign: assignFn,
                }),
            },
        },
    };

    return shallowMount(await Shopware.Component.build('sw-entity-many-to-many-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-entity-many-to-many-select', () => {
    it('should be a Vue.js component', async () => {
        const swEntityManyToManySelect = await createEntityManyToManySelect();

        expect(swEntityManyToManySelect.vm).toBeTruthy();
    });

    it('should use the given criteria to search the repository', async () => {
        const swEntityManyToManySelect = await createEntityManyToManySelect();

        swEntityManyToManySelect.vm.sendSearchRequest();

        expect(swEntityManyToManySelect.vm.searchRepository.search).toHaveBeenCalledWith(criteria, Shopware.Context.api);
    });

    it('should use advanced selection submit', async () => {
        const swEntityManyToManySelect = await createEntityManyToManySelect();

        swEntityManyToManySelect.vm.onAdvancedSelectionSubmit([]);

        expect(deleteFn).toHaveBeenCalledTimes(3);

        const searchResult = [{ id: utils.createId(), name: 'new entry' }];
        swEntityManyToManySelect.vm.displaySearch(new EntityCollection(
            '/test-entity',
            'testEntity',
            { isShopwareContext: true },
            new Criteria(1, 25),
            searchResult,
            searchResult.length,
            null,
        ));
        swEntityManyToManySelect.vm.onAdvancedSelectionSubmit(searchResult);

        expect(assignFn).toHaveBeenCalledTimes(1);
    });
});
