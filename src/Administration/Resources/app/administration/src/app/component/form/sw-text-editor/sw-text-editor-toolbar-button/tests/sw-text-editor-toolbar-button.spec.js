/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    document.body.innerHTML = '<div id="app"></div>';

    return mount(await wrapTestComponent('sw-text-editor-toolbar-button', { sync: true }), {
        attachTo: document.getElementById('app'),
        props: {
            buttonConfig: {
                type: 'link',
                expanded: true,
                value: '',
                icon: 'regular-link-xs',
            },
        },
        global: {
            stubs: {
                'sw-text-editor-link-menu': await wrapTestComponent('sw-text-editor-link-menu', { sync: true }),
                'sw-text-editor-toolbar-table-button': true,
                'mt-icon': true,
                'sw-entity-single-select': true,
                'sw-category-tree-field': true,
                'sw-media-field': true,
            },
        },
    });
}

async function setupTest(wrapper, viewportWidth = 1200) {
    await flushPromises();

    const linkButton = wrapper.find('.sw-text-editor-toolbar-button__icon');
    await linkButton.trigger('click');
    await flushPromises();

    const vm = wrapper.vm;
    vm.$device = { getViewportWidth: jest.fn().mockReturnValue(viewportWidth) };

    const flyoutEl = vm.$refs.flyoutLinkMenu;
    expect(flyoutEl).toBeInstanceOf(HTMLElement);

    return { vm, flyoutEl };
}

function mockElementDimensions(iconEl, flyoutEl, iconRight, flyoutWidth = 400) {
    Object.defineProperty(iconEl, 'clientWidth', { value: 24, configurable: true });
    Object.defineProperty(flyoutEl, 'clientWidth', { value: flyoutWidth, configurable: true });
    iconEl.getBoundingClientRect = () => ({ right: iconRight });
}

describe('components/form/sw-text-editor/sw-text-editor-toolbar-button', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        document.getSelection().removeAllRanges();
    });

    it('should center flyout over icon when inside a modal and overflow occurs at the modal right bound', async () => {
        const wrapper = await createWrapper();

        const modalDiv = document.createElement('div');
        modalDiv.className = 'mt-modal';
        document.body.appendChild(modalDiv);
        modalDiv.appendChild(document.getElementById('app'));

        const { vm, flyoutEl } = await setupTest(wrapper, 1200);

        mockElementDimensions(vm.$el, flyoutEl, 980);
        modalDiv.getBoundingClientRect = () => ({ right: 1000 });

        await vm.positionLinkMenu();

        expect(flyoutEl.style.getPropertyValue('--flyoutLinkLeftOffset')).toBe('-188px');
        expect(flyoutEl.style.getPropertyValue('--arrow-position')).toBe('200px');
    });

    it('should use viewport fallback when not in a modal and overflow occurs to the right', async () => {
        const wrapper = await createWrapper();

        const { vm, flyoutEl } = await setupTest(wrapper, 900);

        mockElementDimensions(vm.$el, flyoutEl, 980);

        await vm.positionLinkMenu();

        expect(flyoutEl.style.getPropertyValue('--flyoutLinkLeftOffset')).toBe('-506px');
        expect(flyoutEl.style.getPropertyValue('--arrow-position')).toBe('516px');
    });

    it('should keep default offset and arrow when there is no overflow', async () => {
        const wrapper = await createWrapper();

        const { vm, flyoutEl } = await setupTest(wrapper, 1400);

        mockElementDimensions(vm.$el, flyoutEl, 500, 300);

        await vm.positionLinkMenu();

        expect(flyoutEl.style.getPropertyValue('--flyoutLinkLeftOffset')).toBe('0px');
        expect(flyoutEl.style.getPropertyValue('--arrow-position')).toBe('10px');
    });
});
