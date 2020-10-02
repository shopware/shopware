import { createLocalVue, mount } from '@vue/test-utils';
import 'src/app/component/app/sw-app-action-button';
import 'src/app/component/base/sw-icon';

function createWrapper(action, listeners = {}) {
    const localVue = createLocalVue();

    return mount(Shopware.Component.build('sw-app-action-button'), {
        localVue,
        listeners,
        propsData: {
            action
        },
        stubs: {
            'sw-icon': Shopware.Component.build('sw-icon'),
            'icons-default-action-external': {
                template: '<span class="sw-icon sw-icon--default-action-external"></span>'
            }
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
    openNewTab: false,
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

    it('should be a Vue.js component', () => {
        wrapper = createWrapper(baseAction);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'sw-app-action-button',
            'sw-context-menu-item'
        ]));
    });

    it('is a div if action is a webaction', () => {
        wrapper = createWrapper(baseAction);

        expect(wrapper.vm.$el).toBeInstanceOf(HTMLDivElement);
    });

    it('is an anchor if action is a link', () => {
        wrapper = createWrapper({
            ...baseAction,
            openNewTab: true
        });

        expect(wrapper.vm.$el).toBeInstanceOf(HTMLAnchorElement);
        expect(wrapper.attributes('href')).toBe(baseAction.url);
        expect(wrapper.attributes('target')).toBe('_blank');
    });

    it('should render a icon if set', () => {
        wrapper = createWrapper(baseAction);

        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'sw-context-menu-item--icon'
        ]));

        const icon = wrapper.find('img.sw-app-action-button__icon');

        expect(icon.attributes('src')).toBe(`data:image/png;base64, ${baseAction.icon}`);
    });

    it('does not render an icon if not present', () => {
        wrapper = createWrapper({
            ...baseAction,
            icon: null
        });

        expect(wrapper.classes()).toEqual(expect.not.arrayContaining([
            'sw-context-menu-item--icon'
        ]));

        const icon = wrapper.find('img.sw-app-action-button__icon');
        expect(icon.exists()).toBe(false);
    });

    it('emits call to action if it is not a link', async () => {
        const actionListener = jest.fn();

        wrapper = createWrapper(baseAction, {
            'run-app-action': actionListener
        });

        await wrapper.trigger('click');

        expect(actionListener).toBeCalled();
        expect(actionListener).toBeCalledWith(appActionId);
    });

    it('follows the link if clicked', async () => {
        const actionListener = jest.fn();

        wrapper = createWrapper({
            ...baseAction,
            openNewTab: true
        }, {
            'run-app-action': actionListener
        });

        await wrapper.trigger('click');

        expect(actionListener).not.toBeCalled();
    });
});
