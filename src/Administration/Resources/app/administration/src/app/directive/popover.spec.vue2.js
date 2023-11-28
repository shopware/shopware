import popover from 'src/app/directive/popover.directive';
import { createLocalVue, shallowMount } from '@vue/test-utils';

const createWrapper = () => {
    const localVue = createLocalVue();

    const div = document.createElement('div');
    div.id = 'root';
    document.body.appendChild(div);

    const dragdropComponent = {
        name: 'popover-component',
        template: `
            <div
                class="popover-component"
                v-popover="popoverConfig"
            >
                <h1>Hello</h1>
            </div>
        `,
        computed: {
            popoverConfig() {
                return {
                    active: true,
                    resizeWidth: 400,
                };
            },
        },
    };

    return shallowMount(dragdropComponent, {
        localVue,
        attachTo: '#root',
    });
};

const setBoundingClientAndWindow = ({
    elWidth = 120,
    elHeight = 120,
    elTop = 0,
    elLeft = 0,
    elBottom = 0,
    elRight = 0,
    windowWidth = 1920,
    windowHeight = 1080,
}) => {
    Element.prototype.getBoundingClientRect = jest.fn(() => {
        return {
            width: elWidth,
            height: elHeight,
            top: elTop,
            left: elLeft,
            bottom: elBottom,
            right: elRight,
        };
    });

    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: windowHeight,
    });

    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: windowWidth,
    });
};

describe('directives/popover', () => {
    const eventListener = {};

    beforeAll(() => {
        window.addEventListener = jest.fn((event, cb) => {
            eventListener[event] = cb;
        });

        window.removeEventListener = jest.fn((event) => {
            delete eventListener[event];
        });
    });

    beforeEach(() => {
        setBoundingClientAndWindow({});
    });

    it('should be empty', async () => {
        expect(popover.virtualScrollingElements.size).toBe(0);
    });

    it('should add an element', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div'),
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);

        popover.virtualScrollingElements.delete(123);
    });

    it('should remove an element', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div'),
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);

        popover.unregisterVirtualScrollingElement(mockContext._uid);

        expect(popover.virtualScrollingElements.size).toBe(0);
    });

    it('should not have an event listener', async () => {
        expect(eventListener).not.toHaveProperty('scroll');
    });

    it('should add an event listener', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div'),
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);
        expect(eventListener).toHaveProperty('scroll');

        popover.unregisterVirtualScrollingElement(mockContext._uid);
    });

    it('should remove an event listener', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div'),
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);
        expect(eventListener).toHaveProperty('scroll');

        popover.unregisterVirtualScrollingElement(mockContext._uid);

        expect(eventListener).not.toHaveProperty('scroll');
    });

    it('should open the popover to the bottom right', async () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('--placement-top-outside');
        expect(wrapper.classes()).toContain('--placement-left-outside');
    });

    it('should open the popover to the bottom even when there is not enough space (but more than at the top)', async () => {
        setBoundingClientAndWindow({
            elTop: 100,
            elBottom: 110,
            windowHeight: 1000,
        });

        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('--placement-top-outside');
        expect(wrapper.classes()).toContain('--placement-left-outside');
    });

    it('should open the popover to the top when there is more space than at the bottom', async () => {
        setBoundingClientAndWindow({
            elTop: 120,
            elBottom: 110,
            windowHeight: 1000,
        });

        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('--placement-top-outside');
        expect(wrapper.classes()).toContain('--placement-left-outside');
    });

    it('should open the popover to the top right', async () => {
        setBoundingClientAndWindow({
            elTop: 1000,
            elBottom: 1000,
        });

        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('--placement-bottom-outside');
        expect(wrapper.classes()).toContain('--placement-left-outside');
    });

    it('should open the popover to the top left', async () => {
        setBoundingClientAndWindow({
            elTop: 1000,
            elBottom: 1000,
            elLeft: 1000,
            elRight: 1000,
        });

        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('--placement-bottom-outside');
        expect(wrapper.classes()).toContain('--placement-right-outside');
    });
});
