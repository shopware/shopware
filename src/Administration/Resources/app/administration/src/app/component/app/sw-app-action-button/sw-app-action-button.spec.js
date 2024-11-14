/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(action) {
    return mount(await wrapTestComponent('sw-app-action-button', { sync: true }), {
        props: {
            action,
        },
        global: {
            directives: {
                tooltip: {},
            },
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'icons-regular-external-link': {
                    template: '<span class="sw-icon sw-icon--regular-external-link"></span>',
                },
                'sw-extension-icon': await wrapTestComponent('sw-extension-icon'),
            },
            provide: {
                acl: { can: () => true },
            },
        },
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
        'en-GB': 'Add product',
    },
    url: 'http://test-url/actions/product/add',
};

describe('sw-app-action-button', () => {
    let wrapper = null;

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(baseAction);

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining([
                'sw-app-action-button',
                'sw-context-menu-item',
            ]),
        );
    });

    it('is a div if action is a webaction', async () => {
        wrapper = await createWrapper(baseAction);

        expect(wrapper.vm.$el).toBeInstanceOf(HTMLDivElement);
    });

    it('should render a icon if set', async () => {
        wrapper = await createWrapper(baseAction);
        await flushPromises();

        expect(wrapper.classes()).toEqual(
            expect.arrayContaining([
                'sw-context-menu-item--icon',
            ]),
        );

        const icon = wrapper.find('img.sw-extension-icon__icon');

        expect(icon.attributes('src')).toBe(`data:image/png;base64, ${baseAction.icon}`);
    });

    it('does not render an icon if not present', async () => {
        wrapper = await createWrapper({
            ...baseAction,
            icon: null,
        });

        expect(wrapper.classes()).toEqual(
            expect.not.arrayContaining([
                'sw-context-menu-item--icon',
            ]),
        );

        const icon = wrapper.find('img.sw-extension-icon__icon');
        expect(icon.exists()).toBe(false);
    });

    it('should emit call to action', async () => {
        wrapper = await createWrapper(baseAction);

        await wrapper.trigger('click');

        expect(wrapper.emitted('run-app-action')[0]).toStrictEqual([
            baseAction,
        ]);
    });
});
