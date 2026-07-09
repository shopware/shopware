/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import component from './index';

async function createWrapper(props = {}) {
    return mount(component, {
        props,
        global: {
            stubs: {
                'sw-upload-listener': true,
                'sw-media-compact-upload-v2': true,
            },
        },
    });
}

describe('src/module/sw-settings/component/sw-system-config-media-upload', () => {
    it('should pass the same upload tag to the upload listener and compact uploader', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent({ name: 'sw-upload-listener' }).props('uploadTag')).toBe(
            wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).props('uploadTag'),
        );
    });

    it('should emit the uploaded media id on upload finish', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent({ name: 'sw-upload-listener' }).vm.$emit('media-upload-finish', { targetId: 'media-id' });

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)).toEqual(['media-id']);
    });

    it('should emit the selected media id on selection change', async () => {
        const wrapper = await createWrapper();

        await wrapper
            .getComponent({ name: 'sw-media-compact-upload-v2' })
            .vm.$emit('selection-change', [{ id: 'media-id' }]);

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)).toEqual(['media-id']);
    });

    it('should emit null when the current image is removed', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).vm.$emit('media-upload-remove-image');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)).toEqual([null]);
    });
});
