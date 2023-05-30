import { shallowMount, createLocalVue } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/form/select/entity/sw-entity-many-to-many-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/base/sw-label';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/base/sw-product-variant-info';

const fixture = [
    { id: utils.createId(), name: 'first entry' },
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        new Criteria(1, 25),
        fixture,
        fixture.length,
        null,
    );
}

const createSelect = async (customOptions) => {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    const options = {
        localVue,
        stubs: {
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-label': true,
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
        },
        propsData: {
            entityCollection: getCollection(),
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: (value) => Promise.resolve({ id: value, name: value }),
                        search: () => Promise.resolve(getCollection()),
                    };
                },
            },
        },
    };

    return shallowMount(await Shopware.Component.build('sw-entity-many-to-many-select'), {
        ...options,
        ...customOptions,
    });
};

describe('components/sw-entity-multi-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createSelect();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use the provided associations in the criteria', async () => {
        const criteria = new Criteria(1, 25);
        criteria.addAssociation('testAssociation');
        const entityCollection = getCollection();
        entityCollection.context = 'test';

        const checkAssociation = jest.fn(searchCriteria => {
            expect(searchCriteria.associations).toHaveLength(1);
            expect(searchCriteria.associations[0].association).toBe('testAssociation');
        });

        const wrapper = await createSelect({
            propsData: {
                entityCollection: entityCollection,
                criteria: criteria,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: (searchCriteria, context) => {
                                // The sendSearchRequest function does not use the entity context.
                                // This check filters the fetchDisplayItems function search request
                                if (context !== 'test') {
                                    checkAssociation(searchCriteria);
                                }
                                return Promise.resolve(
                                    new EntityCollection(
                                        '',
                                        '',
                                        Shopware.Context.api,
                                        new Criteria(1, 1),
                                        [],
                                        0,
                                    ),
                                );
                            },
                        };
                    },
                },
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');
        expect(checkAssociation).toHaveBeenCalled();
    });
});
