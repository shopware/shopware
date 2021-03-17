import { mount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-adding-success';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-circle-icon';
import 'src/app/component/base/sw-label';

describe('src/module/sw-extension/component/sw-extension-adding-success', () => {
    let wrapper;

    beforeEach(() => { if (wrapper) wrapper.destroy(); });

    function createWrapper() {
        return mount(Shopware.Component.build('sw-extension-adding-success'), {
            stubs: {
                'sw-button': Shopware.Component.build('sw-button'),
                'sw-circle-icon': Shopware.Component.build('sw-circle-icon'),
                'sw-label': Shopware.Component.build('sw-label'),
                'sw-icon': true
            },
            mocks: {
                $tc: (key) => key
            }
        });
    }

    it('passes correct props to sw-circle-icon', () => {
        wrapper = createWrapper();

        expect(wrapper.get('.sw-circle-icon').props('variant')).toBe('success');
        expect(wrapper.get('.sw-circle-icon').props('size')).toBe(72);
        expect(wrapper.get('.sw-circle-icon').props('iconName')).toBe('default-basic-checkmark-line');
    });

    it('has a primary block button', () => {
        wrapper = createWrapper();

        const closeButton = wrapper.get('button.sw-button');

        expect(closeButton.props('variant')).toBe('primary');
        expect(closeButton.props('block')).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        wrapper = createWrapper();

        await wrapper.get('button.sw-button').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });
});

