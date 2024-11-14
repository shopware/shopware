import { mount } from '@vue/test-utils';

/**
 * @package services-settings
 */
async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-extension-icon', { sync: true }), {
        props: {
            ...props,
        },
    });
}

describe('src/module/sw-extension/component/sw-extension-icon', () => {
    it('passes correct props to image', async () => {
        const wrapper = await createWrapper({ src: 'path-to-icon' });

        const image = wrapper.get('img');

        expect(image.classes('sw-extension-icon__icon')).toBe(true);
        expect(image.attributes('src')).toBe('path-to-icon');
        expect(image.attributes('alt')).toBe('');
    });

    it('can take an alt text', async () => {
        const wrapper = await createWrapper({
            src: 'path-to-icon',
            alt: 'description of an image',
        });

        const image = wrapper.get('img');

        expect(image.classes('sw-extension-icon__icon')).toBe(true);
        expect(image.attributes('src')).toBe('path-to-icon');
        expect(image.attributes('alt')).toBe('description of an image');
    });
});
