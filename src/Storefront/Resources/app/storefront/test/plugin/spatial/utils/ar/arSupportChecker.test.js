import { supportQuickLook, supportWebXR, supportsAr } from 'src/plugin/spatial/utils/ar/arSupportChecker';

/**
 * @package innovation
 */
describe('arSupportChecker', () => {
    let anchor = undefined;
    let supportsMock = undefined;
    navigator.xr = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        function makeAnchor() {
            return {
                relList: {
                    supports: jest.fn()
                },
            };
        }

        anchor = makeAnchor();
        jest.spyOn(document, 'createElement').mockReturnValue(anchor);
        supportsMock = jest.spyOn(anchor.relList, 'supports').mockReturnValue(false);
    });

    describe('supportQuickLook', () => {
        test('should check if the ar feature is checked', () => {
            supportQuickLook();
            expect(supportsMock).toHaveBeenCalledWith('ar');
        });

        test('should return true/false if it does support QuickLook or not', () => {
            supportsMock = jest.spyOn(anchor.relList, 'supports').mockReturnValue(true);
            expect(supportQuickLook()).toBe(true);
            supportsMock = jest.spyOn(anchor.relList, 'supports').mockReturnValue(false);
            expect(supportQuickLook()).toBe(false);
        });
    });

    describe('supportWebXR', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should return false if WebXr is not supported', async () => {
            const supported = await supportWebXR();
            expect(supported).toBe(false); // false due to navigator.xr mock to be false
        });

        test('should return true/false is immersive session is supported or not', async() => {
            navigator.xr = {
                isSessionSupported: jest.fn().mockReturnValue(true)
            };
            let supported = await supportWebXR();

            expect(navigator.xr.isSessionSupported).toHaveBeenCalledWith('immersive-ar');
            expect(supported).toBe(true);

            navigator.xr.isSessionSupported = jest.fn().mockReturnValue(false);
            supported = await supportWebXR();

            expect(navigator.xr.isSessionSupported).toHaveBeenCalledWith('immersive-ar');
            expect(supported).toBe(false);
        });
    });

    describe('supportsAr', () => {
        test('should return true if webxr or iosquicklook is supported', async () => {
            expect(await supportsAr()).toBe(false);

            supportsMock = jest.spyOn(anchor.relList, 'supports').mockReturnValue(true);

            expect(await supportsAr()).toBe(true);

            supportsMock = jest.spyOn(anchor.relList, 'supports').mockReturnValue(false);
            navigator.xr = {
                isSessionSupported: jest.fn().mockReturnValue(true)
            };

            expect(await supportsAr()).toBe(true);
        });
    });
});
