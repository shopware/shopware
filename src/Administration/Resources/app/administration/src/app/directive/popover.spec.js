import popover from 'src/app/directive/popover.directive';

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

    it('should be empty', async () => {
        expect(popover.virtualScrollingElements.size).toBe(0);
    });

    it('should add an element', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div')
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);

        popover.virtualScrollingElements.delete(123);
    });

    it('should remove an element', async () => {
        const mockElement = document.createElement('h1');
        const mockContext = {
            _uid: 123,
            $el: document.createElement('div')
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
            $el: document.createElement('div')
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
            $el: document.createElement('div')
        };

        popover.registerVirtualScrollingElement(mockElement, mockContext);

        expect(popover.virtualScrollingElements.size).toBe(1);
        expect(eventListener).toHaveProperty('scroll');

        popover.unregisterVirtualScrollingElement(mockContext._uid);

        expect(eventListener).not.toHaveProperty('scroll');
    });
});
