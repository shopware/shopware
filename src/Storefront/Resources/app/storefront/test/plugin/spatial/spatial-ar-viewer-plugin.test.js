import SpatialArViewerPlugin from 'src/plugin/spatial/spatial-ar-viewer-plugin';
import SpatialObjectLoaderUtil from 'src/plugin/spatial/utils/spatial-object-loader-util';
import iosQuickLook from 'src/plugin/spatial/utils/ar/iosQuickLook';
import WebXrView from 'src/plugin/spatial/utils/ar/WebXrView';
import { supportQuickLook, supportsAr, supportWebXR } from 'src/plugin/spatial/utils/ar/arSupportChecker';
import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';
jest.mock('src/plugin/spatial/utils/ar/arSupportChecker');
jest.mock('src/plugin/spatial/utils/ar/WebXrView');
jest.mock('src/plugin/spatial/utils/ar/iosQuickLook');
jest.mock('src/plugin/spatial/utils/spatial-object-loader-util');
jest.mock('src/plugin/spatial/utils/spatial-threejs-load-util');

/**
 * @package innovation
 */
describe('SpatialArViewerPlugin', () => {
    let SpatialArViewerPluginObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <div data-spatial-ar-viewer
                 data-spatial-ar-viewer-options='{ "spatialArId": "1" }'
                 data-spatial-model-url="testurl">
            </div>
            <div class="ar-qr-modal">
                <canvas data-ar-model-id="1"></canvas>
            </div>
            <div class="ar-qr-modal-open-session">
                <button class="ar-btn-open-session" data-modal-open-ar-session-autostart="1"></button>
            </div>
        `;

        window.autostartingARView = null;

        delete window.location;
        window.location = {
            ancestorOrigins: null,
            hash: null,
            host: 'test.com',
            port: '80',
            protocol: 'http:',
            hostname: 'test.com',
            href: 'http://test.com?autostartAr=1',
            origin: 'http://test.com',
            pathname: null,
            search: '?autostartAr=1',
            assign: null,
            reload: null,
            replace: null,
        };

        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));
        iosQuickLook.mockReturnValue(Promise.resolve('123'));
        supportsAr.mockReturnValue(true);

        SpatialArViewerPluginObject = new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'), {
            spatialArId : "1"
        });
        SpatialArViewerPluginObject.model = "1";
        const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show')
            .mockReturnValue({});
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialArViewerPlugin is instantiated', () => {
        SpatialArViewerPluginObject = new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'));
        expect(SpatialArViewerPluginObject instanceof SpatialArViewerPlugin).toBe(true);
    });

    test('should call startARView when clicking the plugin element', async () => {
        const startARViewSpy = jest.spyOn(SpatialArViewerPluginObject, 'startARView');

        expect(startARViewSpy).not.toHaveBeenCalled();

        document.querySelector('[data-spatial-ar-viewer]').dispatchEvent(new Event('click'));

        expect(startARViewSpy).toHaveBeenCalled();
    });

    test('SpatialObjectLoaderUtil constructor should not be called if there is no model url', async () => {
        expect(SpatialObjectLoaderUtil).toHaveBeenCalledTimes(1); // called already with the original template including model url
        document.body.innerHTML = `<div data-spatial-ar-viewer></div>`; // overwrite template without model url data attribute

        SpatialArViewerPluginObject = await new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'));

        expect(SpatialObjectLoaderUtil).toHaveBeenCalledTimes(1); // the number of calls should not have increased
    });

    describe('.startARView', () => {
        let startWebXRViewSpy = undefined;
        let startIOSQuickLookSpy = undefined;

        beforeEach(() => {
            jest.clearAllMocks();
            startWebXRViewSpy = jest.spyOn(SpatialArViewerPluginObject, 'startWebXRView');
            startIOSQuickLookSpy = jest.spyOn(SpatialArViewerPluginObject, 'startIOSQuickLook');
        });

        test('should define a function', () => {
            expect(typeof SpatialArViewerPluginObject.startARView).toBe('function');
        });

        test('should call startWebXRView if its supported', async () => {
            supportWebXR.mockReturnValue(Promise.resolve(true));

            expect(startWebXRViewSpy).not.toHaveBeenCalled();

            await SpatialArViewerPluginObject.startARView();

            expect(startWebXRViewSpy).toHaveBeenCalled();
        });

        test('should start AR session if open ar button is clicked', async () => {
            supportWebXR.mockReturnValue(Promise.resolve(true));
            SpatialArViewerPluginObject.model = "1";
            const startARViewSpy = jest.spyOn(SpatialArViewerPluginObject, 'startARView');

            expect(startARViewSpy).not.toHaveBeenCalled();

            document.querySelector('.ar-btn-open-session')?.click();

            expect(startARViewSpy).toHaveBeenCalled();
        });

        test('should call startIOSQuickLook if WebXR is not supported but IOSQuickLook does', async () => {
            supportWebXR.mockReturnValue(Promise.resolve(false));
            supportQuickLook.mockReturnValue(true);

            expect(startIOSQuickLookSpy).not.toHaveBeenCalled();

            await SpatialArViewerPluginObject.startARView();

            expect(startIOSQuickLookSpy).toHaveBeenCalled();
        });

        test('should call Modal show() if AR not supported', async () => {
            supportsAr.mockReturnValue(false);
            SpatialArViewerPluginObject = await new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'));
            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show')
                .mockReturnValue({});

            expect(modalShowSpy).not.toHaveBeenCalled();

            await SpatialArViewerPluginObject.startARView();

            expect(modalShowSpy).toHaveBeenCalled();

            SpatialArViewerPluginObject = await new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'), {
                spatialArId : "1"
            });

            modalShowSpy.mockClear()

            await SpatialArViewerPluginObject.startARView();
            expect(modalShowSpy).toHaveBeenCalled();
        });
    });
});
