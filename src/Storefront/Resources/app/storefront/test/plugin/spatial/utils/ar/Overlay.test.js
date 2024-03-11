import Overlay from 'src/plugin/spatial/utils/ar/Overlay';

/**
 * @package innovation
 */
describe('Overlay', () => {
    const overlayHtml = document.createElement('div');
    overlayHtml.setAttribute('data-spatial-ar-overlay', '');
    overlayHtml.classList.add('spatial-ar-overlay');
    overlayHtml.innerHTML = `
        <button
            data-spatial-ar-overlay-exit
            class="spatial-ar-exit-button"
        >
        </button>
        <div class="spatial-ar-placement-hint">
            <div class="">
                <span class="text-center">Tap to place the object</span>
            </div>
            <div class="progress">
                <div data-spatial-ar-overlay-progress
                     class="progress-bar"
                     role="progressbar"
                     style="width: 0"
                     aria-valuenow="0"
                     aria-valuemin="0"
                     aria-valuemax="100"
                ></div>
            </div>
        </div>
        <div class="spatial-ar-movement-hint">
            <div class="">
                <div class="ar-anim-container">
                </div>
                <span class="text-center">Move the device to start</span>
            </div>
        </div>
    `;
    let overlayObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();
        overlayObject = new Overlay(overlayHtml);
    });

    afterEach(()=>{
        jest.useRealTimers();
    });

    test('Overlay is instantiated', () => {
        expect(overlayObject.overlay.classList.contains(Overlay.options.classes.visible)).toBe(true);
        expect(overlayObject.overlay.classList.contains(Overlay.options.classes.loading)).toBe(true);
        expect(overlayObject.overlay.classList.contains(Overlay.options.classes.placementHint)).toBe(true);
        expect(overlayObject.exitButton instanceof HTMLElement).toBe(true);
        expect(overlayObject.progressBar instanceof HTMLElement).toBe(true);
    });

    describe('.sessionStarted', () => {
        test('defines a function', () => {
            expect(typeof overlayObject.sessionStarted).toBe('function');
        });

        test('removes loading class and adds session running class', () => {
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.loading)).toBe(true);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.sessionRunning)).toBe(false);
            overlayObject.sessionStarted();
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.loading)).toBe(false);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.sessionRunning)).toBe(true);
        });
    });

    describe('.sessionEnded', () => {
        test('defines a function', () => {
            expect(typeof overlayObject.sessionEnded).toBe('function');
        });

        test('removes loading, session running, visible, placement hint and tracking classes', () => {
            overlayObject.sessionStarted();
            overlayObject.trackingStarted();
            overlayObject.overlay.classList.add(Overlay.options.classes.loading);

            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.loading)).toBe(true);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.sessionRunning)).toBe(true);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.visible)).toBe(true);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.placementHint)).toBe(true);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.tracking)).toBe(true);

            overlayObject.sessionEnded();

            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.loading)).toBe(false);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.sessionRunning)).toBe(false);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.visible)).toBe(false);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.placementHint)).toBe(false);
            expect(overlayObject.overlay.classList.contains(Overlay.options.classes.tracking)).toBe(false);
        });
    });

    describe('.addExitListener', () => {
        test('defines a function', () => {
            expect(typeof overlayObject.addExitListener).toBe('function');
        });

        test('adds exit callback listener', () => {
            const exitCallback = () => {
                return true;
            }
            const exitListenerSpy = jest.spyOn(overlayObject.exitButton, 'addEventListener')

            expect(exitListenerSpy).not.toHaveBeenCalled();

            overlayObject.addExitListener(exitCallback);

            expect(exitListenerSpy).toHaveBeenCalledWith('click', exitCallback);
        });
    });

    describe('.removeExitListener', () => {
        test('defines a function', () => {
            expect(typeof overlayObject.removeExitListener).toBe('function');
        });

        test('removes exit callback listener', () => {
            const exitCallback = () => {
                return true;
            }
            const exitListenerSpy = jest.spyOn(overlayObject.exitButton, 'removeEventListener')

            expect(exitListenerSpy).not.toHaveBeenCalled();

            overlayObject.addExitListener(exitCallback);
            overlayObject.removeExitListener(exitCallback);

            expect(exitListenerSpy).toHaveBeenCalledWith('click', exitCallback);
        });
    });

    describe('.element', () => {
        test('return the overlay element', () => {
            expect(overlayObject.element instanceof HTMLElement).toBe(true);
            expect(overlayObject.element).toBe(overlayObject.overlay);
        });
    });

    describe('.startProgress', () => {
        test('defines a function', () => {
            expect(typeof overlayObject.startProgress).toBe('function');
        });

        test('progress advance with time and placement hint class is removed at the end', () => {
            expect(overlayObject.progress).toBe(0);

            jest.advanceTimersByTime(Overlay.options.placementHintTimeout/10);

            expect(overlayObject.overlay.classList.contains( Overlay.options.classes.placementHint )).toBe(true);
            expect(overlayObject.progress).toBe(10);

            jest.advanceTimersByTime(Overlay.options.placementHintTimeout);

            expect(overlayObject.overlay.classList.contains( Overlay.options.classes.placementHint )).toBe(false);
        });
    });
});
