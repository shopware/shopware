/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customPropsData = {}, customStubs = {}, search = jest.fn().mockResolvedValue([])) {
    const entityCollection = [];
    entityCollection.context = {
        languageId: '1a2b3c',
    };

    return mount(
        await wrapTestComponent('sw-many-to-many-assignment-card', {
            sync: true,
        }),
        {
            props: {
                columns: [],
                entityCollection: entityCollection,
                localMode: true,
                ...customPropsData,
            },
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div><slot></slot><slot name="grid"></slot></div>',
                    },
                    'sw-select-base': {
                        template: '<div class="sw-select-base"></div>',
                    },
                    'sw-data-grid': {
                        template: '<div><slot name="actions"></slot></div>',
                    },
                    'sw-context-menu': true,
                    'sw-context-menu-item': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-pagination': true,
                    'sw-product-variant-info': true,
                    ...customStubs,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({ search }),
                    },
                },
            },
        },
    );
}

describe('src/app/component/entity/sw-many-to-many-assignment-card', () => {
    it('should have an enabled sw-select-base', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled sw-select-base', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an enabled context menu item', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled context menu item', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    describe('clear button of the select', () => {
        async function createWrapperWithSelectBase(props = {}, search = jest.fn().mockResolvedValue([])) {
            const wrapper = await createWrapper(
                props,
                {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-help-text': true,
                    'sw-ai-copilot-badge': true,
                    'sw-inheritance-switch': true,
                    'sw-loader': true,
                    'mt-icon': true,
                },
                search,
            );

            await flushPromises();

            return wrapper;
        }

        async function clickClear(wrapper) {
            await wrapper.find('.sw-select__select-indicator-hitbox').trigger('click');
            await flushPromises();
        }

        it('should clear the search input', async () => {
            const wrapper = await createWrapperWithSelectBase();

            const searchInput = wrapper.find('.sw-entity-many-to-many-select input');
            await searchInput.setValue('shirt');
            expect(searchInput.element.value).toBe('shirt');

            await clickClear(wrapper);

            expect(wrapper.vm.searchTerm).toBeNull();
            expect(searchInput.element.value).toBe('');
        });

        it('should reload the unfiltered results when the select is expanded', async () => {
            const search = jest.fn().mockResolvedValue([]);
            const wrapper = await createWrapperWithSelectBase({}, search);

            await wrapper.find('.sw-select__selection').trigger('click');
            await flushPromises();
            await wrapper.setData({ searchTerm: 'shirt' });
            search.mockClear();

            await clickClear(wrapper);

            expect(search).toHaveBeenCalledTimes(1);
            expect(search.mock.calls[0][0].term).toBeNull();
        });

        it('should ignore results of a request that was started before the clear', async () => {
            const search = jest.fn().mockResolvedValue([]);
            const wrapper = await createWrapperWithSelectBase({}, search);

            await wrapper.find('.sw-select__selection').trigger('click');
            await flushPromises();
            await wrapper.setData({ searchTerm: 'shirt' });

            let resolveStaleSearch;
            search.mockReturnValueOnce(new Promise((resolve) => (resolveStaleSearch = resolve)));
            wrapper.vm.onSelectExpanded();

            const unfilteredResults = [{ id: 'unfiltered' }];
            search.mockResolvedValueOnce(unfilteredResults);
            await clickClear(wrapper);

            resolveStaleSearch([{ id: 'stale' }]);
            await flushPromises();

            expect(wrapper.vm.resultCollection).toEqual(unfilteredResults);
        });

        it('should reload the grid without the search term when the select is collapsed', async () => {
            const search = jest.fn().mockResolvedValue([]);
            const entityCollection = Object.assign([], { context: { languageId: '1a2b3c' }, getIds: () => [] });
            const wrapper = await createWrapperWithSelectBase({ localMode: false, entityCollection }, search);

            await wrapper.setData({ searchTerm: 'shirt' });
            search.mockClear();

            await clickClear(wrapper);

            expect(search).toHaveBeenCalledTimes(1);
            expect(search.mock.calls[0][0].term).toBeNull();
        });

        it('should not reload anything in local mode when the select is collapsed', async () => {
            const search = jest.fn().mockResolvedValue([]);
            const wrapper = await createWrapperWithSelectBase({}, search);

            await wrapper.setData({ searchTerm: 'shirt' });
            search.mockClear();

            await clickClear(wrapper);

            expect(wrapper.vm.searchTerm).toBeNull();
            expect(search).not.toHaveBeenCalled();
        });
    });
});
