import XrLighting from 'src/plugin/spatial/utils/ar/webXr/XrLighting';

/**
 * @package innovation
 */
describe('XrLighting', () => {
    let XrLightingObject = undefined;
    let renderer = undefined;
    let scene = undefined;
    let onEstimationStartSpy = undefined;
    let onEstimationEndSpy = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        renderer = {};
        scene = {
            add: jest.fn(),
            remove: jest.fn()
        };
        global.XRFrame = jest.fn();
        window.threeJs = {};
        window.threeJs.HemisphereLight = function () {
            return {
                position: {
                    set: jest.fn()
                }
            }
        };
        window.threeJsAddons = {};
        window.threeJsAddons.XREstimatedLight = function () {
            const testLight = document.createElement('div');
            testLight.environment = 123;
            return testLight;
        };
        onEstimationStartSpy = jest.spyOn(XrLighting.prototype, 'onEstimationStart');
        onEstimationEndSpy = jest.spyOn(XrLighting.prototype, 'onEstimationEnd');
        XrLightingObject = new XrLighting(scene, renderer);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('XrLighting is instantiated', () => {
        expect(XrLightingObject instanceof XrLighting).toBe(true);
    });

    test('Should call onEstimationStart() when estimationstart event is triggered', async () => {
        expect(onEstimationStartSpy).not.toHaveBeenCalled();

        XrLightingObject.xrLight.dispatchEvent(new Event('estimationstart'));

        expect(onEstimationStartSpy).toHaveBeenCalled();
    });

    test('Should call onEstimationEnd() when estimationend event is triggered', async () => {
        expect(onEstimationEndSpy).not.toHaveBeenCalled();

        XrLightingObject.xrLight.dispatchEvent(new Event('estimationend'));

        expect(onEstimationEndSpy).toHaveBeenCalled();
    });

    describe('.dispose', () => {
        test('should define a function', () => {
            expect(typeof XrLightingObject.dispose).toBe('function');
        });

        test('should call removeEventListeners for estimationstart and estimationend', () => {
            const removeEventListenerSpy = jest.spyOn(XrLightingObject.xrLight, 'removeEventListener');

            expect(removeEventListenerSpy).not.toHaveBeenCalledWith('estimationstart', expect.anything());
            expect(removeEventListenerSpy).not.toHaveBeenCalledWith('estimationend', expect.anything());

            XrLightingObject.dispose();

            expect(removeEventListenerSpy).toHaveBeenCalledWith('estimationstart', expect.anything());
            expect(removeEventListenerSpy).toHaveBeenCalledWith('estimationend', expect.anything());
        });
    });
});
