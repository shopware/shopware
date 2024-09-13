/**
 * @package customer-order
 */

import { mount } from '@vue/test-utils';
import topBarButtonState from 'src/app/store/topbar-button.store';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-app-topbar-button', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
            },
            provide: {
                acl: { can: () => true },
            },
        },
    });
}

const topbarButton = {
    label: 'Upgrade',
    icon: 'solid-rocket',
    callback: () => {},
};

describe('sw-app-topbar-button', () => {
    Shopware.Store.register(topBarButtonState);
    let wrapper = null;

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render button correctly', async () => {
        const store = Shopware.Store.get('topBarButtonState');
        store.buttons.push(topbarButton);

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.text()).toEqual(topbarButton.label);
    });

    it('should able to click button', async () => {
        const store = Shopware.Store.get('topBarButtonState');
        store.buttons.push(topbarButton);

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        const spyOnButtonClick = jest.spyOn(topbarButton, 'callback');
        await button.trigger('click');

        expect(spyOnButtonClick).toHaveBeenCalled();
    });
});
