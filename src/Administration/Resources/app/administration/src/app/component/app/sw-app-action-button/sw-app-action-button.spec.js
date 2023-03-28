/**
 * @package admin
 */

import { createLocalVue, mount } from '@vue/test-utils';
import 'src/app/component/app/sw-app-action-button';
import 'src/app/component/base/sw-icon';
import swExtensionIcon from 'src/module/sw-extension/component/sw-extension-icon';

Shopware.Component.register('sw-extension-icon', swExtensionIcon);

async function createWrapper(action, listeners = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(await Shopware.Component.build('sw-app-action-button'), {
        localVue,
        listeners,
        propsData: {
            action
        },
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'icons-regular-external-link': {
                template: '<span class="sw-icon sw-icon--regular-external-link"></span>'
            },
            'sw-extension-icon': await Shopware.Component.build('sw-extension-icon'),
        },
        provide: {
            acl: { can: () => true }
        }
    });
}

const appActionId = Shopware.Utils.createId();

const baseAction = {
    id: appActionId,
    action: 'addProduct',
    app: 'TestApp',
    icon: 'someBase64Icon',
    label: {
        'de-DE': 'Product hinzufügen',
        'en-GB': 'Add product'
    },
    url: 'http://test-url/actions/product/add'
};

describe('sw-app-action-button', () => {
    let wrapper = null;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(baseAction);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'sw-app-action-button',
            'sw-context-menu-item'
        ]));
    });

    it('is a div if action is a webaction', async () => {
        wrapper = await createWrapper(baseAction);

        expect(wrapper.vm.$el).toBeInstanceOf(HTMLDivElement);
    });

    it('should render a icon if set', async () => {
        wrapper = await createWrapper(baseAction);

        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'sw-context-menu-item--icon'
        ]));

        const icon = wrapper.find('img.sw-extension-icon__icon');

        expect(icon.attributes('src')).toBe(`data:image/png;base64, ${baseAction.icon}`);
    });

    it('does not render an icon if not present', async () => {
        wrapper = await createWrapper({
            ...baseAction,
            icon: null
        });

        expect(wrapper.classes()).toEqual(expect.not.arrayContaining([
            'sw-context-menu-item--icon'
        ]));

        const icon = wrapper.find('img.sw-extension-icon__icon');
        expect(icon.exists()).toBe(false);
    });

    it('should emit call to action', async () => {
        const actionListener = jest.fn();

        wrapper = await createWrapper(baseAction, {
            'run-app-action': actionListener
        });

        await wrapper.trigger('click');

        expect(actionListener).toBeCalled();
        expect(actionListener).toBeCalledWith(baseAction);
    });
});
