/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

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

    it('should stay hidden while no extension added a section', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.isVisible()).toBe(false);
    });

    it('should show once an extension adds a section to its position', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();

        expect(wrapper.isVisible()).toBe(true);
    });

    it('should name the panel as a landmark for screen readers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('aside').exists()).toBe(true);
        expect(wrapper.attributes('aria-label')).toBe('global.sw-extension-floating-section.label');
    });

    it('should be inert until its content is measured', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();

        expect(wrapper.attributes('inert')).toBeDefined();
    });

    it('should be inert while the extensions show nothing, and active again once they do', async () => {
        const wrapper = await createWrapper();

        addFloatingSection();
        await flushPromises();

        await reportContentHeight(320);
        expect(wrapper.attributes('inert')).toBeUndefined();

        await reportContentHeight(0);
        expect(wrapper.attributes('inert')).toBeDefined();
    });

    it('should stop measuring when it is removed', async () => {
        const wrapper = await createWrapper();

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

        expect(wrapper.isVisible()).toBe(false);
    });

    it('should render the component sections of its position', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-extension-component-section-stub').attributes('position-identifier')).toBe(
            'sw-desktop__floating',
        );
    });
});
