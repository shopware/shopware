import { mount } from '@vue/test-utils';
import swExtensionAddingSuccess from 'src/module/sw-extension/component/sw-extension-adding-success';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-circle-icon';
import 'src/app/component/base/sw-label';

Shopware.Component.register('sw-extension-adding-success', swExtensionAddingSuccess);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-adding-success', () => {
    let wrapper;

    beforeEach(async () => { if (wrapper) wrapper.destroy(); });

    async function createWrapper() {
        return mount(await Shopware.Component.build('sw-extension-adding-success'), {
            stubs: {
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-circle-icon': await Shopware.Component.build('sw-circle-icon'),
                'sw-label': await Shopware.Component.build('sw-label'),
                'sw-icon': true,
            },
        });
    }

    it('passes correct props to sw-circle-icon', async () => {
        wrapper = await createWrapper();

        expect(wrapper.get('.sw-circle-icon').props('variant')).toBe('success');
        expect(wrapper.get('.sw-circle-icon').props('size')).toBe(72);
        expect(wrapper.get('.sw-circle-icon').props('iconName')).toBe('regular-checkmark');
    });

    it('has a primary block button', async () => {
        wrapper = await createWrapper();

        const closeButton = wrapper.get('button.sw-button');

        expect(closeButton.props('variant')).toBe('primary');
        expect(closeButton.props('block')).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        wrapper = await createWrapper();

        await wrapper.get('button.sw-button').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });
});

