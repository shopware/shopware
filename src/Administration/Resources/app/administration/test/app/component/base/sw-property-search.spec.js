import { shallowMount } from '@vue/test-utils';
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

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-property-search'), {
        propsData: {
            options: [
                {}
            ]
        },
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': '<div></div>',
            'sw-container': '<div><slot></slot></div>',
            'sw-grid': Shopware.Component.build('sw-grid'),
            'sw-pagination': Shopware.Component.build('sw-pagination'),
            'sw-grid-row': Shopware.Component.build('sw-grid-row'),
            'sw-grid-column': Shopware.Component.build('sw-grid-column'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': '<div></div>',
            'sw-checkbox-field': '<div class="checkbox"></div>'
        },
        mocks: {
            $tc: (translationPath) => translationPath,
            $device: { onResize: () => {} }
        },
        provide: {
            validationService: {}
        }
    });
}

const groups = [];

for (let i = 0; i < 12; i += 1) {
    groups.push({
        isDeleted: false,
        isLoading: false,
        errors: [],
        versionId: '__vue_devtool_undefined__',
        id: `${i}c909198131346e299b93aa60dd40eeb`,
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
            customFields: []
        },
        relationships: null,
        options: [],
        type: 'property_group',
        meta: {},
        translations: [],
        optionCount: 3,
        getAssociation() {
            return Shopware.StateDeprecated.getStore('property_group_option');
        }
    });
}

const options = [];

for (let i = 0; i < 12; i += 1) {
    options.push({
        groupId: '1c909198131346e299b93aa60dd40eeb',
        name: 'darkbrown',
        position: i + 1,
        colorHexCode: null,
        mediaId: null,
        customFields: null,
        createdAt: '2020-06-02T13:03:33+00:00',
        updatedAt: null,
        translated: { name: 'darkbrown', position: 1, customFields: [] },
        id: `${i}66e8d9b5ce24916896d29e27a9e1763`,
        translations: [],
        productConfiguratorSettings: [],
        productProperties: [],
        productOptions: []
    });
}

describe('components/base/sw-property-search', () => {
    beforeAll(() => {
        Shopware.StateDeprecated.registerStore('property_group', {
            getList: async () => {
                return {
                    items: groups,
                    total: groups.length
                };
            }
        });

        Shopware.StateDeprecated.registerStore('property_group_option', {
            getList: async () => {
                return {
                    items: options,
                    total: options.length
                };
            }
        });
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have a pagination element inside group grid', async () => {
        const wrapper = createWrapper();

        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const paginationElement = wrapper.find('.sw-pagination');

        expect(paginationElement.exists()).toBe(true);
    });

    it('should have pagination with two buttons inside group grid', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const amountOfPages = wrapper.findAll('.sw-pagination__list-item').length;

        expect(amountOfPages).toBe(2);
    });

    it('should change group page when paginating', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.groupPage).toBe(1);

        const nextPageButton = wrapper.find('.sw-pagination__list-button:not(.is-active)');
        nextPageButton.trigger('click');

        expect(wrapper.vm.groupPage).toBe(2);
    });

    it('should open options grid after clicking on property group', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const groupElement = wrapper.find('.group_grid__column-name');
        groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const optionElement = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-grid__row--0');

        expect(optionElement.exists()).toBe(true);
    });

    it('should have a pagination for the option grid', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const groupElement = wrapper.find('.group_grid__column-name');
        groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const paginationElement = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-pagination');
        expect(paginationElement.exists()).toBe(true);
    });

    it('should have multiple pages for option grid', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const groupElement = wrapper.find('.group_grid__column-name');
        groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const amountOfOptionPages = wrapper.findAll(
            '.sw-property-search__tree-selection__option_grid .sw-pagination .sw-pagination__list-button'
        ).length;

        expect(amountOfOptionPages).toBe(2);
    });

    it('should change the option page when clicking pagination', async () => {
        const wrapper = createWrapper();
        wrapper.vm.onFocusSearch();

        await wrapper.vm.$nextTick();

        const groupElement = wrapper.find('.group_grid__column-name');
        groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.optionPage).toBe(1);

        // eslint-disable-next-line max-len
        const nextPageButton = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-pagination__list-button:not(.is-active)');
        nextPageButton.trigger('click');

        expect(wrapper.vm.optionPage).toBe(2);
    });

    it('should keep text when entering something into the search input', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        const searchInput = wrapper.find('#sw-field--searchTerm');

        // check if input is empty
        expect(searchInput.element.value).toBe('');

        // entering text into input field
        searchInput.setValue('color');

        // check if content of input field is not empty
        expect(searchInput.element.value).toBe('color');
    });
});
