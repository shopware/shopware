/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], resetError = false) {
    return mount(await wrapTestComponent('sw-settings-search-excluded-search-terms', {
        sync: true,
    }), {
        props: {
            searchConfigs: {
                excludedTerms: ['i', 'a', 'on', 'in', 'of', 'at', 'right', 'he', 'she', 'we', 'us', 'our'],
            },
        },

        global: {
            renderStubDefaultSlot: true,
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: () => ({
                        save: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                excludedSearchTermService: {
                    resetExcludedSearchTerm: jest.fn(() => {
                        if (resetError === true) {
                            return Promise.reject();
                        }
                        return Promise.resolve();
                    }),
                },
            },

            stubs: {
                'sw-card': {
                    template: `
                    <div class="sw-card">
                        <slot name="toolbar"></slot>
                        <slot name="grid"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-empty-state': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-container': true,
                'sw-card-filter': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-data-grid-column-position': await wrapTestComponent('sw-data-grid-column-position'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-pagination': await wrapTestComponent('sw-pagination'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-icon': true,
                'sw-select-field': true,
                'sw-popover': {
                    props: ['popoverClass'],
                    template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                },
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-data-grid-skeleton': true,
                'sw-loader': true,
            },
        },

    });
}

describe('module/sw-settings-search/component/sw-settings-search-excluded-search-terms', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be show element no excluded search', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();
        await wrapper.setProps({
            searchConfigs: {
                excludedTerms: [],
            },
        });
        await flushPromises();

        expect(wrapper.vm.searchConfigs.excludedTerms).toEqual([]);
        expect(wrapper.find('.sw-settings-search-excluded-search-terms').exists()).toBeTruthy();
        expect(wrapper.find('.sw-empty-state').exists()).toBeTruthy();
    });

    it('should have pagination on list excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const pagination = wrapper.find('.sw-data-grid__pagination');
        const pages = wrapper.findAll('.sw-pagination__list-item');
        expect(pagination.exists()).toBe(true);
        expect(pages).toHaveLength(2);
    });

    it('should have listing excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const firstValue = wrapper.vm.searchConfigs.excludedTerms[0];
        const dataGrids = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(dataGrids).toHaveLength(10);
        expect(dataGrids.at(0).text()).toEqual(firstValue);
    });

    it('should not able to delete excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const firstRowContext = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        await firstRowContext.find('.sw-data-grid__cell--actions .sw-context-button__button')
            .trigger('click');
        await flushPromises();
        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();
        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();
        expect(deleteButton.classes()).toContain('is--disabled');
    });

    it('should be able to delete excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.deleter',
        ]);
        wrapper.vm.createNotificationSuccess = jest.fn();
        await flushPromises();

        const firstRowContext = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        await firstRowContext.find('.sw-data-grid__cell--actions .sw-context-button__button')
            .trigger('click');
        await flushPromises();
        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();
        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();
        await deleteButton.trigger('click');
        await flushPromises();
        const firstRowAfterDelete = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        expect(firstRowAfterDelete.text()).not.toEqual(firstRowContext.text());

        const checkBox = firstRowAfterDelete.find('.sw-field__checkbox input');
        await checkBox.setChecked();
        await flushPromises();
        expect(wrapper.find('.sw-data-grid__bulk-selected.sw-data-grid__bulk-selected-count').text()).toBe('1');
        const bulkButton = wrapper.find('.sw-data-grid__bulk button');
        expect(bulkButton).toBeTruthy();
        await bulkButton.trigger('click');
        await flushPromises();
        const firstRowAfterBulkDelete = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        expect(firstRowAfterDelete.text()).not.toEqual(firstRowAfterBulkDelete.text());
    });

    it('should not able to add a new excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const addExcludedTermButton = wrapper.find('.sw-button.sw-button--ghost.sw-button--small');
        expect(addExcludedTermButton.attributes().disabled).toBeDefined();
    });


    it('should allow add excluded terms', async () => {
        const wrapper = await createWrapper([
            'product_search_config.creator',
        ]);
        await flushPromises();

        const firstValue = wrapper.vm.searchConfigs.excludedTerms[0];
        const addExcludedTermButton = wrapper.find('.sw-button.sw-button--ghost.sw-button--small');
        await addExcludedTermButton.trigger('click');

        const firstRow = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        expect(firstRow.text()).not.toEqual(firstValue);
    });

    it('should be render component', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const dataGridsFirstLoading = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(dataGridsFirstLoading).toHaveLength(10);

        const paginationGrids = wrapper.findAll('.sw-pagination li');
        await paginationGrids.at(1).find('button').trigger('click');
        await flushPromises();

        const dataGridsSecondPage = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(dataGridsSecondPage).toHaveLength(2);
    });

    it('should not able to reset excluded search term to default', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const btnResetToDefault = wrapper.find('.sw-settings-search-excluded-search-terms__reset-button');
        expect(btnResetToDefault.attributes().disabled).toBeDefined();
    });

    it('should able to reset excluded search term to default with success message', async () => {
        const wrapper = await createWrapper([
            'product_search_config.creator',
        ]);
        wrapper.vm.createNotificationSuccess = jest.fn();
        await flushPromises();

        const btnResetToDefault = wrapper.find('.sw-settings-search-excluded-search-terms__reset-button');
        expect(btnResetToDefault.attributes().disable).not.toBeTruthy();
        await btnResetToDefault.trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.resetToDefaultExcludedTermSuccess',
        });
    });

    it('should not able to reset excluded search term to default with error message', async () => {
        const wrapper = await createWrapper([
            'product_search_config.creator',
        ], true);

        wrapper.vm.createNotificationError = jest.fn();
        await flushPromises();

        const btnResetToDefault = wrapper.find('.sw-settings-search-excluded-search-terms__reset-button');
        expect(btnResetToDefault.attributes().disable).not.toBeTruthy();
        await btnResetToDefault.trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.resetToDefaultExcludedTermError',
        });
    });
});
