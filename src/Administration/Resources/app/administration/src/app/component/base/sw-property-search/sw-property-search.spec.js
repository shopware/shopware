/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-property-search', { sync: true }),
        {
            props: {
                options: [
                    {},
                ],
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': {
                        template: '<div></div>',
                    },
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-grid': await wrapTestComponent('sw-grid'),
                    'sw-pagination': await wrapTestComponent('sw-pagination'),
                    'sw-grid-row': await wrapTestComponent('sw-grid-row'),
                    'sw-grid-column': await wrapTestComponent('sw-grid-column'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-icon': {
                        template: '<div></div>',
                    },
                    'sw-checkbox-field': {
                        template: '<div class="checkbox"></div>',
                    },
                    'sw-empty-state': true,
                    'mt-text-field': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'mt-button': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-select-field': true,
                },
                provide: {
                    validationService: {},
                    repositoryFactory: {
                        create: (entity) => ({
                            search: () => {
                                if (entity === 'property_group') {
                                    const response = [];
                                    const count = 12;

                                    for (let i = 0; i < count; i += 1) {
                                        const group = {
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
                                                name: 'Länge',
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
                                        };

                                        group.options.entity = 'property_group_option';

                                        response.push(group);
                                    }

                                    response.total = count;

                                    return Promise.resolve(response);
                                }

                                if (entity === 'property_group_option') {
                                    const response = [];
                                    const count = 12;

                                    for (let i = 0; i < count; i += 1) {
                                        response.push({
                                            groupId: '1c909198131346e299b93aa60dd40eeb',
                                            name: 'darkgreen',
                                            position: i + 1,
                                            colorHexCode: null,
                                            mediaId: null,
                                            customFields: null,
                                            createdAt: '2020-06-02T13:03:33+00:00',
                                            updatedAt: null,
                                            translated: { name: 'Dunkelgrün', position: 1, customFields: [] },
                                            id: `${i}66e8d9b5ce24916896d29e27a9e1763`,
                                            translations: [],
                                            group: {
                                                versionId: '__vue_devtool_undefined__',
                                                id: `${i}c909198131346e299b93aa60dd40eeb`,
                                                name: 'length',
                                                description: null,
                                                displayType: 'text',
                                                sortingType: 'alphanumeric',
                                            },
                                            productConfiguratorSettings: [],
                                            productProperties: [],
                                            productOptions: [],
                                        });
                                    }

                                    response.total = count;
                                    return Promise.resolve(response);
                                }

                                const response = [];
                                response.total = 0;
                                return Promise.resolve(response);
                            },
                        }),
                    },
                },
            },
        },
    );
}

describe('components/base/sw-property-search', () => {
    afterEach(async () => {
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a pagination element inside group grid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const paginationElement = wrapper.find('.sw-pagination');

        expect(paginationElement.exists()).toBe(true);
    });

    it('should have pagination with two buttons inside group grid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const amountOfPages = wrapper.findAll('.sw-pagination__list-item').length;

        expect(amountOfPages).toBe(2);
    });

    it('should change group page when paginating', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        expect(wrapper.vm.groupPage).toBe(1);

        const nextPageButton = wrapper.find('.sw-pagination__list-button:not(.is-active)');
        await nextPageButton.trigger('click');

        expect(wrapper.vm.groupPage).toBe(2);
    });

    it('should open options grid after clicking on property group', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const optionElement = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-grid__row--0');

        expect(optionElement.exists()).toBe(true);
    });

    it('should have a pagination for the option grid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const paginationElement = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-pagination');
        expect(paginationElement.exists()).toBe(true);
    });

    it('should have multiple pages for option grid', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const amountOfOptionPages = wrapper.findAll(
            '.sw-property-search__tree-selection__option_grid .sw-pagination .sw-pagination__list-button',
        ).length;

        expect(amountOfOptionPages).toBe(2);
    });

    it('should change the option page when clicking pagination', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.optionPage).toBe(1);

        // eslint-disable-next-line max-len
        const nextPageButton = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-pagination__list-button:not(.is-active)');
        await nextPageButton.trigger('click');

        expect(wrapper.vm.optionPage).toBe(2);
    });

    it('should keep text when entering something into the search input', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const searchInput = wrapper.find('#sw-field--searchTerm');

        // check if input is empty
        expect(searchInput.element.value).toBe('');

        // entering text into input field
        await searchInput.setValue('color');

        // check if content of input field is not empty
        expect(searchInput.element.value).toBe('color');
    });

    it('should change the group options when clicking pagination', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        let groupOptions = wrapper.findAll('.sw-property-search__tree-selection__option_grid--option-value').length;

        expect(wrapper.vm.optionPage).toBe(1);
        expect(groupOptions).toBe(10);

        // eslint-disable-next-line max-len
        const nextPageButton = wrapper.find('.sw-property-search__tree-selection__option_grid .sw-pagination__list-button:not(.is-active)');
        await nextPageButton.trigger('click');

        await wrapper.vm.$nextTick();

        groupOptions = wrapper.findAll('.sw-property-search__tree-selection__option_grid--option-value').length;

        expect(wrapper.vm.optionPage).toBe(2);
        expect(groupOptions).toBe(2);
    });

    it('should display translated property groups and property group options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onFocusSearch();
        await flushPromises();

        const groupElement = wrapper.find('.group_grid__column-name');
        await groupElement.trigger('click');

        await wrapper.vm.$nextTick();

        const groupOptionElement = wrapper.find('.sw-property-search__tree-selection__option_grid--option-value');

        expect(groupElement.find('.sw-grid__cell-content').text()).toBe('Länge');
        expect(groupOptionElement.find('.sw-grid__cell-content').text()).toBe('Dunkelgrün');
    });
});
