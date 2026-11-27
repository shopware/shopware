/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

const floatingUiStub = {
    props: [
        'isOpened',
        'anchorElement',
        'floatingUiOptions',
    ],
    template: '<div><slot v-if="isOpened"></slot></div>',
};

const popoverConfigDeprecation = 'sw-media-field.popoverConfig is deprecated.';

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
                    'mt-floating-ui': floatingUiStub,
                    'sw-media-upload-v2': true,
                    'sw-upload-listener': true,
                    'sw-simple-search-field': true,
                    'sw-loader': true,
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

    it('should only render the label when one is given', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-media-field__label').exists()).toBe(false);

        await wrapper.setProps({ label: 'Media' });

        expect(wrapper.find('.sw-media-field__label').text()).toBe('Media');
    });

    it('should stop propagation when the picker content is clicked', async () => {
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

    // @deprecated tag:v6.8.0 - The test will be removed with sw-media-field.popoverConfig.
    it.deprecated('v6.8.0.0')('returns empty config when picker is false', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.popoverConfig).toEqual({});
        expect(deprecations).toHaveTriggered(popoverConfigDeprecation);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with sw-media-field.popoverConfig.
    it.deprecated('v6.8.0.0')('returns empty config when not inside a modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });

        wrapper.element.closest = jest.fn(() => null);
        expect(wrapper.vm.popoverConfig).toEqual({});
        expect(deprecations).toHaveTriggered(popoverConfigDeprecation);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with sw-media-field.popoverConfig.
    it.deprecated('v6.8.0.0')('returns modal targetSelector when picker open inside modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });

        const el = wrapper.element;
        el.closest = jest.fn((selector) => (selector === '.mt-modal' ? el : null));

        const config = wrapper.vm.$options.computed.popoverConfig.call(wrapper.vm);
        expect(config).toEqual({
            targetSelector: '.mt-modal',
        });
        expect(deprecations).toHaveTriggered(popoverConfigDeprecation);
    });

    it('should render sw-upload-listener with correct upload tag and auto-upload', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });

        const uploadListener = wrapper.find('sw-upload-listener-stub');

        expect(uploadListener.exists()).toBe(true);
        expect(uploadListener.attributes('upload-tag')).toBe(wrapper.vm.uploadTag);
        expect(uploadListener.attributes()).toHaveProperty('auto-upload');
    });

    it('should set media id and close picker when upload finishes', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true, showUploadField: true });

        const targetId = 'new-media-id-123';
        wrapper.vm.exposeNewId({ targetId });

        expect(wrapper.emitted('update:value')).toEqual([[targetId]]);
        expect(wrapper.vm.showUploadField).toBe(false);
        expect(wrapper.vm.showPicker).toBe(false);
    });

    it('anchors the picker to the toggle button and aligns it with the button end', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });
        const floatingUi = wrapper.getComponent(floatingUiStub);

        expect(floatingUi.props('anchorElement')).toBe(wrapper.get('.sw-media-field__toggle-button').element);
        expect(floatingUi.props('floatingUiOptions')).toEqual({ placement: 'bottom-end' });
    });
});
