import { shallowMount } from '@vue/test-utils';
import swExtensionIcon from 'src/module/sw-extension/component/sw-extension-icon';

Shopware.Component.register('sw-extension-icon', swExtensionIcon);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-icon', () => {
    it('passes correct props to image', async () => {
        const wrapper = await shallowMount(
            await Shopware.Component.build('sw-extension-icon'),
            {
                propsData: {
                    iconSrc: 'path-to-icon',
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
                    iconSrc: 'path-to-icon',
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
