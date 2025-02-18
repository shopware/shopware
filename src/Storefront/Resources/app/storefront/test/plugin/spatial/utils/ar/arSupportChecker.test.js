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

        test('should return true on quick look enabled browsers', () => {
            jest.spyOn(anchor.relList, 'supports').mockReturnValue(false);

            // These are real user agents that support QuickLook
            // These are supported browsers
            const validAgents = [
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/118.0.5993.69 Mobile/15E148 Safari/604.1", // Chrome
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) EdgiOS/126.0.2592.106 Version/16.0 Mobile/15E148 Safari/604.1", // Edge
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/132.0.6834.208 Mobile/15E148 Safari/604.1", // Vivaldi
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Mobile/15E148 Safari/604.1 Ddg/16.1", // DuckDuckGo
            ];

            validAgents.forEach((ua) => {
                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPhone'));
                expect(supportQuickLook()).toBe(true);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPad'));
                expect(supportQuickLook()).toBe(true);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPod'));
                expect(supportQuickLook()).toBe(true);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'Mac'));
                expect(supportQuickLook()).toBe(false);
            });
        });

        test('should return false on quick look un enabled browsers', () => {
            jest.spyOn(anchor.relList, 'supports').mockReturnValue(false);

            // These are real user agents that support QuickLook
            const invalidAgents = [
                // unsupported browsers
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Mobile/15E148 Safari/604.1", // Safari - note that this will have the relList.supports return true normally, if not we should not try to open the quick look
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/128.3 Mobile/15E148 Safari/605.1.15", // Firefox
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Mobile/15E148 Safari/604.1 OPT/5.4.1", // Opera
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148", // Brave
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/356.0.726653311 Mobile/15E148 Safari/604.1", // Google
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 16_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Mobile/15E148 Safari/605.1.15 (Ecosia ios@10.6.0.2082)", // Ecosia

                // andriod browsers
                "Mozilla/5.0 (Linux; Android 10; Pixel 3 XL Build/QP1A.190711.020; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/83.0.4103.106 Mobile Safari/537.36",
                "Mozilla/5.0 (Android 9; Pixel 2 XL; rv:68.0) Gecko/68.0 Firefox/68.0",
                "Mozilla/5.0 (Linux; Android 11; SM-G998B Build/RP1A.200720.012; Samsung; Galaxy S21 Ultra 5G) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.0 Chrome/89.0.4389.90 Mobile Safari/537.36",

                // pre ios 12 - but supported browsers
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 10_3_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/118.0.5993.69 Mobile/14E277 Safari/602.1", // Chrome
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 10_3_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) EdgiOS/40.10.0.0.0 Version/10.0 Mobile/14E277 Safari/602.1", // Edge
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 10_3_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/132.0.6834.208 Mobile/14E277 Safari/602.1", // Vivaldi
                "Mozilla/5.0 ([DEVICE]; CPU [DEVICE] OS 10_3_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) Version/10.0 Mobile/14E277 Safari/602.1 Ddg/10.0", // DuckDuckGo

                // other
                "",
                "Mozilla/5.0",
                "[DEVICE]",
                "OS 16_1 like Mac OS X",
                "CriOS/118.0.5993.69 Mobile/15E148 Safari/604.1",
            ];

            invalidAgents.forEach((ua) => {
                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPhone'));
                expect(supportQuickLook()).toBe(false);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPad'));
                expect(supportQuickLook()).toBe(false);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'iPod'));
                expect(supportQuickLook()).toBe(false);

                jest.spyOn(navigator, 'userAgent', 'get').mockReturnValue(ua.replace('[DEVICE]', 'Mac'));
                expect(supportQuickLook()).toBe(false);
            });
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
