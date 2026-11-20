/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const floatingUiStub = {
    name: 'MtFloatingUi',
    // Typed like the real props, so a bare `detached` attribute is cast to `true`
    props: {
        isOpened: Boolean,
        anchorElement: { type: null },
        floatingUiOptions: Object,
        autoUpdateOptions: Object,
        offset: Number,
        detached: Boolean,
    },
    // Stands in for the teleported panel, onto which the component sets its attributes
    template: '<div class="mt-floating-ui-stub"><div class="mt-floating-ui__content"><slot /></div></div>',
};

let resizeObservers: { callback: ResizeObserverCallback; observed: Element[]; disconnected: boolean }[] = [];

const originalResizeObserver = global.ResizeObserver;

global.ResizeObserver = class {
    private readonly record: (typeof resizeObservers)[number];

    constructor(callback: ResizeObserverCallback) {
        this.record = { callback, observed: [], disconnected: false };
        resizeObservers.push(this.record);
    }

    observe(element: Element) {
        this.record.observed.push(element);
    }

    unobserve() {}

    disconnect() {
        this.record.disconnected = true;
    }
} as unknown as typeof ResizeObserver;

async function reportContentHeight(height: number) {
    const observer = resizeObservers.at(-1)!;
    observer.callback([{ contentRect: { height } } as ResizeObserverEntry], {} as ResizeObserver);
    await flushPromises();
}

function addFloatingSection() {
    Shopware.Store.get('extensionComponentSections').addSection({
        component: 'div',
        positionId: 'sw-desktop__floating',
        props: { locationId: 'my-location' },
        extensionName: 'MyApp',
    });
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-extension-floating-section', { sync: true }), {
        global: {
            stubs: {
                'mt-floating-ui': floatingUiStub,
                'sw-extension-component-section': true,
            },
        },
    });
}

describe('src/app/component/extension-api/sw-extension-floating-section', () => {
    beforeEach(() => {
        Shopware.Store.get('extensionComponentSections').identifier = {};
        resizeObservers = [];
    });

    afterAll(() => {
        global.ResizeObserver = originalResizeObserver;
    });

    it('should stay closed while no extension added a section', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent(floatingUiStub).props('isOpened')).toBe(false);
    });

    it('should open once an extension adds a section to its position', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();

        expect(wrapper.getComponent(floatingUiStub).props('isOpened')).toBe(true);
    });

    it('should name the open panel as a region for screen readers', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();

        const panel = wrapper.find('.mt-floating-ui__content');
        expect(panel.attributes('role')).toBe('region');
        expect(panel.attributes('aria-label')).toBe('global.sw-extension-floating-section.label');
        expect(panel.attributes('inert')).toBeUndefined();
    });

    it('should make the panel inert while the extensions show nothing, and active again once they do', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();
        const panel = wrapper.find('.mt-floating-ui__content');

        await reportContentHeight(0);
        expect(panel.attributes('inert')).toBe('');

        await reportContentHeight(320);
        expect(panel.attributes('inert')).toBeUndefined();
    });

    it('should stop measuring when it is removed', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();
        wrapper.unmount();

        expect(resizeObservers.at(-1)?.disconnected).toBe(true);
    });

    it('should ignore sections of other positions', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('extensionComponentSections').addSection({
            component: 'div',
            positionId: 'sw-dashboard__before-content',
            props: { locationId: 'my-location' },
            extensionName: 'MyApp',
        });
        await flushPromises();

        expect(wrapper.getComponent(floatingUiStub).props('isOpened')).toBe(false);
    });

    it('should float detached above the corner it marks', async () => {
        const wrapper = await createWrapper();
        const floatingUi = wrapper.getComponent(floatingUiStub);

        expect(floatingUi.props('anchorElement')).toBe(wrapper.element);
        expect(floatingUi.props('floatingUiOptions')).toEqual({ placement: 'top-start' });
        // Follows the anchor when the layout moves it, for example when the menu collapses
        expect(floatingUi.props('autoUpdateOptions')).toEqual({ layoutShift: true });
        expect(floatingUi.props('offset')).toBe(0);
        expect(floatingUi.props('detached')).toBe(true);
    });

    it('should render the component sections of its position', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-extension-component-section-stub').attributes('position-identifier')).toBe(
            'sw-desktop__floating',
        );
    });
});
