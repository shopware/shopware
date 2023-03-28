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

        expect(image.classes('sw-extension-icon')).toBe(true);
        expect(image.attributes('src')).toBe('path-to-icon');

        wrapper.destroy();
    });
});
