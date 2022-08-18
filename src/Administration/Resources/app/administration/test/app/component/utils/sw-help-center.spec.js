import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-help-center';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/utils/sw-popover';

function createWrapper() {
    return shallowMount(
        Shopware.Component.build('sw-help-center'), {
            localVue: createLocalVue(),
            stubs: {
                'sw-context-button': Shopware.Component.build('sw-context-button'),
                'sw-context-menu': Shopware.Component.build('sw-context-menu'),
                'sw-icon': true,
                'sw-popover': Shopware.Component.build('sw-popover'),
                'sw-external-link': true,
            }
        }
    );
}

describe('components/utils/sw-help-center', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should open the context menu when the button is clicked', async () => {
        wrapper = await createWrapper();

        const button = wrapper.get('.sw-context-button');
        await button.trigger('click');

        const contextMenu = wrapper.get('.sw-context-menu');

        expect(contextMenu.get('h3').text()).toEqual('global.sw-help-center.title');
    });
});
