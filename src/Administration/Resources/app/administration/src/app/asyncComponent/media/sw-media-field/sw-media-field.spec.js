/**
 * @package content
 */
import { mount } from '@vue/test-utils';

describe('src/app/component/media/sw-media-field', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-media-field', { sync: true }), {
            props: {
                fileAccept: '*/*',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-media-media-item': true,
                    'sw-button': true,
                    'sw-popover': {
                        template: `
                        <div>
                            <slot />
                        </div>
                    `,
                    },
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-icon': true,
                    'sw-pagination': true,
                },
                mocks: {
                    $route: {
                        query: '',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return Promise.resolve();
                            },
                            get: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve();
                            },
                        }),
                    },
                },
            },
        });
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default folder in criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            defaultFolder: 'product',
        });
        const criteria = wrapper.vm.suggestionCriteria;
        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'mediaFolder.defaultFolder.entity',
            value: 'product',
        });

        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(5);
    });

    it('should contain a property props fileAccept', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$props.fileAccept).toBe('*/*');
    });

    it('should stop propagation when sw-popover content is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showPicker: true,
        });

        const stopPropagation = jest.fn();
        await wrapper.find('.sw-media-field__actions_bar').trigger('click', {
            stopPropagation,
        });

        expect(stopPropagation).toHaveBeenCalled();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.total).toBe(0);
    });

    it('should be able to change search term', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.fetchSuggestions = jest.fn();

        wrapper.vm.onSearchTermChange('test');

        expect(wrapper.vm.searchTerm).toBe('test');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.fetchSuggestions).toHaveBeenCalled();
    });

    it('should be able to change page', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.fetchSuggestions = jest.fn();

        wrapper.vm.onPageChange({
            page: 2,
            limit: 5,
        });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.fetchSuggestions).toHaveBeenCalled();
    });
});
