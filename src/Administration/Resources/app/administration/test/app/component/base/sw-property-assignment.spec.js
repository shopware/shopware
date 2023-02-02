import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';
import 'src/app/component/base/sw-property-assignment';
import 'src/app/component/base/sw-property-search';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/grid/sw-grid-column';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-label';

const propertyFixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        group: {
            name: 'example'
        },
        groupId: 'group1',
        translated: {
            name: 'option'
        }
    },
    {
        id: utils.createId(),
        name: 'second entry',
        group: {
            name: 'example'
        },
        groupId: 'group1',
        translated: {
            name: 'option'
        }
    },
    {
        id: utils.createId(),
        name: 'third',
        group: {
            name: 'entry'
        },
        groupId: 'group2',
        translated: {
            name: 'option'
        }
    }
];

function getPropertyCollection() {
    return new EntityCollection(
        '/property-group-option',
        'property_group_option',
        null,
        { isShopwareContext: true },
        propertyFixture,
        propertyFixture.length,
        null
    );
}

function getPropertyGroups() {
    return [
        {
            name: 'length',
            description: null,
            displayType: 'text',
            sortingType: 'alphanumeric',
            filterable: true,
            position: 1,
            id: 'group1',
            customFields: null,
            createdAt: '2020-06-02T13:03:33+00:00',
            updatedAt: null,
            translated: {
                name: 'Länge',
                description: null,
                position: 1,
                customFields: []
            },
            relationships: null,
            options: [{ name: 'S', translated: { name: 'S' } }, { name: 'M', translated: { name: 'M' } }, { name: 'L', translated: { name: 'L' } }],
            type: 'property_group',
            meta: {},
            translations: [],
            optionCount: 3
        },
        {
            name: 'width',
            description: null,
            displayType: 'text',
            sortingType: 'alphanumeric',
            filterable: true,
            position: 1,
            id: 'group2',
            customFields: null,
            createdAt: '2020-06-02T13:03:33+00:00',
            updatedAt: null,
            translated: {
                name: 'Width',
                description: null,
                position: 1,
                customFields: []
            },
            relationships: null,
            options: [{ name: 'S', translated: { name: 'S' } }, { name: 'M', translated: { name: 'M' } }, { name: 'L', translated: { name: 'L' } }],
            type: 'property_group',
            meta: {},
            translations: [],
            optionCount: 3
        }
    ];
}

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-property-assignment'), {
        data() {
            return {
                groups: getPropertyGroups()
            };
        },
        propsData: {
            propertyCollection: getPropertyCollection(),
            options: [
                {}
            ]
        },
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-property-search': Shopware.Component.build('sw-property-search'),
            'sw-empty-state': Shopware.Component.build('sw-empty-state'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-field-error': {
                template: '<div></div>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-grid': Shopware.Component.build('sw-grid'),
            'sw-pagination': Shopware.Component.build('sw-pagination'),
            'sw-grid-row': Shopware.Component.build('sw-grid-row'),
            'sw-grid-column': Shopware.Component.build('sw-grid-column'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-checkbox-field': {
                template: '<div class="checkbox"></div>'
            }
        },
        mocks: {
            $route: { meta: { $module: { icon: 'default-symbol-content', description: 'Foo bar' } } }
        },
        provide: {
            validationService: {},
            repositoryFactory: {
                create: (entity) => ({
                    search: () => {
                        if (entity === 'property_group') {
                            const response = getPropertyGroups();

                            response.total = 2;

                            return Promise.resolve(response);
                        }

                        const response = [];
                        response.total = 0;
                        return Promise.resolve(response);
                    }
                })
            }
        }
    });
}

describe('components/base/sw-property-assignment', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should removing property item when clicking delete item', async () => {
        const wrapper = createWrapper();

        const spyDeleteOption = jest.spyOn(wrapper.vm, 'deleteOption');
        const spygroupProperties = jest.spyOn(wrapper.vm, 'groupProperties');

        wrapper.vm.groupProperties = jest.fn();
        const option = wrapper.find('.sw-property-assignment__grid_option_column .sw-property-assignment__grid_option_item');

        await option.trigger('mouseenter');

        const beforeRemoveCount = wrapper.vm.propertyCollection.length;

        await option.find('.sw-label__dismiss').trigger('click');

        const afterRemoveCount = wrapper.vm.propertyCollection.length;

        expect(beforeRemoveCount - afterRemoveCount).toEqual(1);
        expect(spyDeleteOption).toHaveBeenCalledTimes(1);
        expect(spygroupProperties).toHaveBeenCalledTimes(0);
    });
});
