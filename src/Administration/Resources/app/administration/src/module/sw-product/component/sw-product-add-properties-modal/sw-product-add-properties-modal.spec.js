/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import swProductAddPropertiesModal from 'src/module/sw-product/component/sw-product-add-properties-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-card-section';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-grid-column';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/base/sw-property-search';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import uuid from '../../../../../test/_helper_/uuid';


Shopware.Component.register('sw-product-add-properties-modal', swProductAddPropertiesModal);

const getPropertyGroupMock = [
    {
        isDeleted: false,
        isLoading: false,
        errors: [],
        versionId: '__vue_devtool_undefined__',
        id: uuid.get('length'),
        name: 'length',
        description: null,
        displayType: 'text',
        sortingType: 'alphanumeric',
        filterable: true,
        position: 1,
        customFields: null,
        createdAt: '2020-06-02T13:03:33+00:00',
        updatedAt: null,
        translated: {
            name: 'length',
            description: null,
            position: 1,
            customFields: [],
        },
        relationships: null,
        options: [],
        type: 'property_group',
        meta: {},
        translations: [],
        optionCount: 3,
    },
];
getPropertyGroupMock.forEach(property => {
    property.options.entity = 'property_group_option';
});
getPropertyGroupMock.total = 1;

const propertyGroupRepositoryMock = {
    search: jest.fn(() => {
        return Promise.resolve([]);
    }),
};

const getPropertyGroupOptionMock = [
    {
        groupId: uuid.get('length'),
        name: 'darkgreen',
        position: 1,
        colorHexCode: null,
        mediaId: null,
        customFields: null,
        createdAt: '2020-06-02T13:03:33+00:00',
        updatedAt: null,
        translated: { name: 'darkgreen', position: 1, customFields: [] },
        id: uuid.get('darkgreen'),
        translations: [],
        group: {
            versionId: '__vue_devtool_undefined__',
            id: uuid.get('length'),
            name: 'length',
            translated: {
                name: 'length',
                description: null,
                position: 1,
                customFields: [],
            },
            description: null,
            displayType: 'text',
            sortingType: 'alphanumeric',
        },
        productConfiguratorSettings: [],
        productProperties: [],
        productOptions: [],
    },
];
getPropertyGroupOptionMock.total = 1;

const propertyGroupOptionRepositoryMock = {
    search: jest.fn(() => {
        return Promise.resolve(getPropertyGroupOptionMock);
    }),
};

const defaultRepositoryMock = {
    search: () => {
        const response = [];
        response.total = 0;
        return Promise.resolve(response);
    },
};

const repositoryMockFactory = (entity) => {
    if (entity === 'property_group') {
        return propertyGroupRepositoryMock;
    }

    if (entity === 'property_group_option') {
        return propertyGroupOptionRepositoryMock;
    }

    return defaultRepositoryMock;
};

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-product-add-properties-modal'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-card-section': await Shopware.Component.build('sw-card-section'),
            'sw-grid': await Shopware.Component.build('sw-grid'),
            'sw-grid-column': await Shopware.Component.build('sw-grid-column'),
            'sw-grid-row': await Shopware.Component.build('sw-grid-row'),
            'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
            'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
            'sw-property-search': await Shopware.Component.build('sw-property-search'),
            'sw-pagination': await Shopware.Component.build('sw-pagination'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
        },
        provide: {
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity),
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
            validationService: {},
        },
        propsData: {
            newProperties: [],
        },
    });
}

describe('src/module/sw-product/component/sw-product-add-properties-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.destroy();
        jest.clearAllMocks();
        jest.clearAllTimers();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit an event when pressing on cancel button', async () => {
        wrapper.vm.onCancel();

        const emitted = wrapper.emitted()['modal-cancel'];
        expect(emitted).toBeTruthy();
    });

    it('should emit an event when pressing on save button', async () => {
        wrapper.vm.onSave();

        const emitted = wrapper.emitted()['modal-save'];
        expect(emitted).toBeTruthy();
    });

    it('should keep text when entering something into the search input', async () => {
        jest.useFakeTimers();

        const searchInput = wrapper.find('.sw-product-add-properties-modal__search input');

        expect(searchInput.element.value).toHaveLength(0);

        await searchInput.setValue('test');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(searchInput.element.value).toBe('test');
    });

    it('should clear search grid after clearing the search term', async () => {
        jest.useFakeTimers();

        const searchInput = wrapper.find('.sw-product-add-properties-modal__search input');

        await searchInput.setValue('d');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        await searchInput.setValue('');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(wrapper.find('.sw-product-add-properties-modal__search').vm.groupOptions).toEqual([]);
    });

    it('should return filters from filter registry', async () => {
        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
