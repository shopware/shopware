import ViewportDetection from 'src/helper/viewport-detection.helper';
import Emitter from 'src/helper/emitter.helper';

const detector = new ViewportDetection();

/**
 * @package storefront
 */
describe('viewport-detection.helper', () => {
    function getContent() {
        if (window.innerWidth < 0) return 'undefined';
        if (window.innerWidth < 576) return 'xs';
        if (window.innerWidth < 768) return 'sm';
        if (window.innerWidth < 992) return 'md';
        if (window.innerWidth < 1200) return 'lg';
        return 'xl';
    }

    function resizeTo (width) {
        window.innerWidth = width;
        window.dispatchEvent(new Event('resize'));
    }

    beforeEach(() => {
        window.getComputedStyle = jest.fn(() => {
            return {
                getPropertyValue: jest.fn(() => {
                    return getContent();
                }),
            }
        });
    });

    test('resize from non existing to xl', () => {
        jest.useFakeTimers();
        const emitter = new Emitter();
        const viewportChanged = jest.fn(),
            isXs = jest.fn(),
            isSm = jest.fn(),
            isMd = jest.fn(),
            isLg = jest.fn(),
            isXl = jest.fn();

        resizeTo(-200);
        jest.runOnlyPendingTimers();

        emitter.subscribe('Viewport/hasChanged', viewportChanged);
        emitter.subscribe('Viewport/isXS', isXs);
        emitter.subscribe('Viewport/isSM', isSm);
        emitter.subscribe('Viewport/isMD', isMd);
        emitter.subscribe('Viewport/isLG', isLg);
        emitter.subscribe('Viewport/isXL', isXl);

        resizeTo(480);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(1);
        expect(isXs).toBeCalledTimes(1);

        resizeTo(640);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(2);
        expect(isSm).toBeCalledTimes(1);

        resizeTo(860);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(3);
        expect(isMd).toBeCalledTimes(1);

        resizeTo(1024);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(4);
        expect(isLg).toBeCalledTimes(1);

        resizeTo(2048);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(5);
        expect(isXl).toBeCalledTimes(1);

        resizeTo(4096);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(5);
        expect(isXl).toBeCalledTimes(1);

        resizeTo(-200);
        jest.runOnlyPendingTimers();

        expect(viewportChanged).toBeCalledTimes(6);
        expect(detector.currentViewport).toBe('UNDEFINED');

    });

    test('detector emits events after content load', () => {
        jest.useFakeTimers();
        const emitter = new Emitter();
        const subscriber = jest.fn();
        emitter.subscribe('Viewport/isXL', subscriber);

        resizeTo(2048);
        window.dispatchEvent(new Event('DOMContentLoaded'));
        jest.runAllTimers();

        expect(subscriber).toBeCalled();
    })
});
