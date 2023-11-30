import WebXrView from 'src/plugin/spatial/utils/ar/WebXrView';
import XrLighting from 'src/plugin/spatial/utils/ar/webXr/XrLighting';
import ObjectPlacement from 'src/plugin/spatial/utils/ar/webXr/ObjectPlacement';
import Overlay from 'src/plugin/spatial/utils/ar/Overlay';

jest.mock('src/plugin/spatial/utils/ar/webXr/XrLighting');
jest.mock('src/plugin/spatial/utils/ar/webXr/ObjectPlacement');
jest.mock('src/plugin/spatial/utils/ar/Overlay');

/**
 * @package innovation
 */
describe('WebXrView', () => {
    let WebXrViewObject = undefined;
    let model = undefined;
    const overlayHtml = document.createElement('div');
    overlayHtml.setAttribute('data-spatial-ar-overlay', '');
    overlayHtml.classList.add('spatial-ar-overlay');
    overlayHtml.innerHTML = `
        <button data-spatial-ar-overlay-exit class="spatial-ar-exit-button"></button>
        <div class="spatial-ar-placement-hint"></div>
        <div class="spatial-ar-movement-hint"></div>
    `;

    beforeEach(() => {
        jest.clearAllMocks();
        model = {
            position: {
                setFromMatrixPosition: jest.fn()
            }
        };
        window.threeJs = {};
        window.threeJs.PerspectiveCamera = function () {
            return {
                position: {
                    set: jest.fn()
                }
            }
        };
        window.threeJs.Scene = function () {
            return {
                add: function () {},
                remove: function () {}
            }
        };
        window.threeJs.WebGLRenderer = function () {
            return {
                setPixelRatio: jest.fn(),
                setSize: jest.fn,
                xr: {
                    enabled: null,
                    getController: () => {
                        return {
                            addEventListener: jest.fn()
                        }
                    },
                    setReferenceSpaceType: jest.fn(),
                    getReferenceSpace: jest.fn(()=> {
                        return {
                            addEventListener: jest.fn()
                        }
                    }),
                    setSession: jest.fn()
                },
                domElement: document.createElement('canvas'),
                setAnimationLoop: jest.fn(),
                render: jest.fn()
            }
        };
        navigator.xr = {
            requestSession: () => {
                return Promise.resolve({
                    addEventListener: jest.fn(),
                    removeEventListener: jest.fn(),
                    end: jest.fn(async ()=>{
                        return Promise.resolve({})
                    })
                })
            }
        };

        WebXrViewObject = new WebXrView(model, overlayHtml);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('WebXrView is instantiated', () => {
        expect(WebXrViewObject instanceof WebXrView).toBe(true);
    });

    describe('.onSessionEnded', () => {
        test('should define a function', () => {
            expect(typeof WebXrViewObject.onSessionEnded).toBe('function');
        });

        test('should reset properties', () => {
            const setAnimationLoopSpy = jest.spyOn(WebXrViewObject.renderer, 'setAnimationLoop');
            const objectPlacementDisposeSpy = jest.spyOn(WebXrViewObject.objectPlacement, 'dispose');
            const lightingDisposeSpy = jest.spyOn(WebXrViewObject.lighting, 'dispose');
            const endSpy = jest.spyOn(WebXrViewObject.session, 'end');
            const sessionEndedSpy = jest.spyOn(WebXrViewObject.overlay, 'sessionEnded');
            setAnimationLoopSpy.mockClear();
            objectPlacementDisposeSpy.mockClear();
            lightingDisposeSpy.mockClear();
            sessionEndedSpy.mockClear();
            endSpy.mockClear();

            expect(setAnimationLoopSpy).not.toHaveBeenCalledWith(null);
            expect(objectPlacementDisposeSpy).not.toHaveBeenCalled();
            expect(lightingDisposeSpy).not.toHaveBeenCalled();
            expect(endSpy).not.toHaveBeenCalled();
            expect(sessionEndedSpy).not.toHaveBeenCalled();

            WebXrViewObject.onSessionEnded();

            expect(objectPlacementDisposeSpy).toHaveBeenCalled();
            expect(lightingDisposeSpy).toHaveBeenCalled();
            expect(setAnimationLoopSpy).toHaveBeenCalledWith(null);
            expect(endSpy).toHaveBeenCalled();
            expect(sessionEndedSpy).toHaveBeenCalled();
        });
    });

    describe('.endSession', () => {
        test('should define a function', () => {
            expect(typeof WebXrViewObject.endSession).toBe('function');
        });

        test('should call session end function', () => {
            const endSpy = jest.spyOn(WebXrViewObject.session, 'end');
            endSpy.mockClear();

            expect(endSpy).not.toHaveBeenCalled();

            WebXrViewObject.endSession();

            expect(endSpy).toHaveBeenCalled();
        });
    });

    describe('.render', () => {
        test('should define a function', () => {
            expect(typeof WebXrViewObject.render).toBe('function');
        });

        test('should call trackingStarted if objectPlacement update returns a hit', () => {
            const updateSpy = jest.spyOn(WebXrViewObject.objectPlacement, 'update');
            const trackingStartedSpy = jest.spyOn(WebXrViewObject.overlay, 'trackingStarted');

            expect(trackingStartedSpy).not.toHaveBeenCalled();

            updateSpy.mockReturnValue(null);
            WebXrViewObject.render();

            expect(trackingStartedSpy).not.toHaveBeenCalled();

            updateSpy.mockReturnValue('123');
            WebXrViewObject.render();

            expect(trackingStartedSpy).toHaveBeenCalled();
        });

        test('should call render function', () => {
            const renderSpy = jest.spyOn(WebXrViewObject.renderer, 'render');

            expect(renderSpy).not.toHaveBeenCalled();

            WebXrViewObject.render();

            expect(renderSpy).toHaveBeenCalled();
        });
    });
});
