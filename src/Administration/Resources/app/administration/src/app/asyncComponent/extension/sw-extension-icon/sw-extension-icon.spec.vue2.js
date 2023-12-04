import { shallowMount } from '@vue/test-utils_v2';
import swExtensionIcon from 'src/app/asyncComponent/extension/sw-extension-icon';

Shopware.Component.register('sw-extension-icon', swExtensionIcon);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-extension-icon', () => {
    it('passes correct props to image', async () => {
        const wrapper = await shallowMount(
            await Shopware.Component.build('sw-extension-icon'),
            {
                propsData: {
                    src: 'path-to-icon',
                },
            },
        );

        const image = wrapper.get('img');

        expect(image.classes('sw-extension-icon__icon')).toBe(true);
        expect(image.attributes('src')).toBe('path-to-icon');
        expect(image.attributes('alt')).toBe('');

        wrapper.destroy();
    });

    it('can take an alt text', async () => {
        const wrapper = await shallowMount(
            await Shopware.Component.build('sw-extension-icon'),
            {
                propsData: {
                    src: 'path-to-icon',
                    alt: 'description of an image',
                },
            },
        );

        const image = wrapper.get('img');

        expect(image.classes('sw-extension-icon__icon')).toBe(true);
        expect(image.attributes('src')).toBe('path-to-icon');
        expect(image.attributes('alt')).toBe('description of an image');

        wrapper.destroy();
    });
});
