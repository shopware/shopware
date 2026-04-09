/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import './index';

describe('sw-theme-list-item', () => {
    async function createWrapper(props = {}) {
        const component = await Shopware.Component.build('sw-theme-list-item');

        return shallowMount(component, {
            props: {
                theme: {
                    id: 'theme-id',
                    previewMedia: null,
                    salesChannels: [],
                    save: jest.fn(),
                    ...props.theme,
                },
                ...props,
            },
            global: {
                stubs: {
                    'sw-icon': true,
                },
                mocks: {
                    $t: (key) => key,
                },
            },
        });
    }

    it('returns preview media style when preview media exists', async () => {
        const wrapper = await createWrapper({
            theme: {
                previewMedia: { id: 'media-id', url: 'https://example.com/image.png' },
            },
        });

        expect(wrapper.vm.previewMedia).toEqual({
            'background-image': "url('https://example.com/image.png')",
            'background-size': 'cover',
        });
    });

    it('falls back to default theme asset when preview media is missing', async () => {
        const assetSpy = jest.spyOn(Shopware.Filter, 'getByName').mockReturnValue(() => 'preview.jpg');
        const wrapper = await createWrapper();

        expect(wrapper.vm.previewMedia).toEqual({
            'background-image': 'url(preview.jpg)',
        });

        assetSpy.mockRestore();
    });

    it('detects active state from sales channels or active prop', async () => {
        const wrapperWithChannel = await createWrapper({
            theme: { salesChannels: [{ id: 'sales-channel-id' }] },
        });
        const wrapperActiveProp = await createWrapper({ active: true });

        expect(wrapperWithChannel.vm.isActive()).toBe(true);
        expect(wrapperActiveProp.vm.isActive()).toBe(true);
    });

    it('builds component classes based on active and disabled', async () => {
        const wrapper = await createWrapper({
            active: true,
            disabled: true,
        });

        expect(wrapper.vm.componentClasses).toEqual({
            'is--active': true,
            'is--disabled': true,
        });
    });

    it('emits preview-image-change when enabled', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onChangePreviewImage(theme);

        expect(wrapper.emitted('preview-image-change')[0]).toEqual([theme]);
    });

    it('does not emit preview-image-change when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        wrapper.vm.onChangePreviewImage({ id: 'theme-id' });

        expect(wrapper.emitted('preview-image-change')).toBeUndefined();
    });

    it('emits item-click when enabled', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onThemeClick();

        expect(wrapper.emitted('item-click')[0]).toEqual([wrapper.props('theme')]);
    });

    it('does not emit item-click when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        wrapper.vm.onThemeClick();

        expect(wrapper.emitted('item-click')).toBeUndefined();
    });

    it('removes preview image and saves theme', async () => {
        const wrapper = await createWrapper({
            theme: {
                previewMediaId: 'media-id',
                previewMedia: { id: 'media-id' },
                save: jest.fn(),
            },
        });
        const theme = wrapper.props('theme');

        wrapper.vm.onRemovePreviewImage(theme);

        expect(theme.previewMediaId).toBeNull();
        expect(theme.previewMedia).toBeNull();
        expect(theme.save).toHaveBeenCalled();
    });

    it('emits theme-delete when enabled', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onDelete(theme);

        expect(wrapper.emitted('theme-delete')[0]).toEqual([theme]);
    });

    it('does not emit theme-delete when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        wrapper.vm.onDelete({ id: 'theme-id' });

        expect(wrapper.emitted('theme-delete')).toBeUndefined();
    });

    it('emits item-click from emitItemClick when enabled', async () => {
        const wrapper = await createWrapper();
        const item = { id: 'item-id' };

        wrapper.vm.emitItemClick(item);

        expect(wrapper.emitted('item-click')[0]).toEqual([item]);
    });

    it('does not emit item-click from emitItemClick when disabled', async () => {
        const wrapper = await createWrapper({ disabled: true });

        wrapper.vm.emitItemClick({ id: 'item-id' });

        expect(wrapper.emitted('item-click')).toBeUndefined();
    });
});
