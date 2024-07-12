/**
 * @package admin
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import utils from 'src/core/service/util.service';

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

const createSelect = async (customOptions = {
    props: {},
    global: {},
}) => {
    return mount(await wrapTestComponent('sw-entity-many-to-many-select', {
        sync: true,
    }), {
        props: {
            entityCollection: getCollection(),
            ...customOptions.props,
        },
        global: {
            stubs: {
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-label': true,
                'sw-loader': await wrapTestComponent('sw-loader'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-button': true,
                'sw-inheritance-switch': true,
                'mt-loader': true,
                'sw-loader-deprecated': true,
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
            ...customOptions.global,
        },
    });
};

describe('components/sw-entity-many-to-many-select', () => {
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
            props: {
                entityCollection: entityCollection,
                criteria: criteria,
            },
            global: {
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
            },
        });

        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        expect(checkAssociation).toHaveBeenCalled();
    });
});
