/**
 * @sw-package framework
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

    it('should show meteor icon if set and view is not item', async () => {
        wrapper = await createWrapper({
            ...baseAction,
            meteorIcon: 'regular-star',
            view: 'list',
        });

        const meteorIcon = wrapper.find('.mt-icon');

        expect(meteorIcon.exists()).toBe(true);
        expect(meteorIcon.classes()).toContain('icon--regular-star');
    });

    it('should not show meteor icon if view is item', async () => {
        wrapper = await createWrapper({
            ...baseAction,
            meteorIcon: 'regular-star',
            view: 'item',
        });

        const meteorIcon = wrapper.find('.mt-icon');
        expect(meteorIcon.exists()).toBe(false);
    });
});
