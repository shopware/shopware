import SpatialArViewerPlugin from 'src/plugin/spatial/spatial-ar-viewer-plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');

window.ARSystem = {
    launch: jest.fn().mockResolvedValue({}),
};

/**
 * @package innovation
 */
describe('SpatialArViewerPlugin', () => {
    let SpatialArViewerPluginObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <div data-spatial-ar-viewer
                 data-spatial-ar-viewer-options='{ "spatialArId": "1", "modelUrl": "testurl" }'>
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

        SpatialArViewerPluginObject = new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'), {
            spatialArId: "1",
            modelUrl: "testurl"
        });
        SpatialArViewerPluginObject.model = "1";
        const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show')
            .mockReturnValue({});

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        window.PluginManager.initializePlugin = jest.fn(() => Promise.resolve());
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

    describe('startARView', () => {
        test('calls ARSystem.launch with correct args', async () => {
            const launchSpy = jest.spyOn(window.ARSystem, 'launch');
            await SpatialArViewerPluginObject.startARView();
            expect(launchSpy).toHaveBeenCalledWith(
                'testurl',
                expect.objectContaining({ arPlacement: 'horizontal', arScale: 'auto' })
            );
        });

        test('on launch error, invokes showARUnsupportedModal with spatialArId', async () => {
            jest.spyOn(window.ARSystem, 'launch').mockRejectedValue(new Error('fail'));
            const showSpy = jest.spyOn(SpatialArViewerPluginObject, 'showARUnsupportedModal');
            await SpatialArViewerPluginObject.startARView();
            expect(showSpy).toHaveBeenCalledWith('1');
        });

        test('on launch error without spatialArId, appends generic modal and shows', async () => {
            jest.spyOn(window.ARSystem, 'launch').mockRejectedValue(new Error('fail'));
            const focusSpy = jest.spyOn(window.focusHandler, 'saveFocusState');
            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});
            const plugin = new SpatialArViewerPlugin(
                document.querySelector('[data-spatial-ar-viewer]'),
                { spatialArId: '', modelUrl: 'testurl' }
            );
            await plugin.startARView();
            expect(focusSpy).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(modalShowSpy).toHaveBeenCalled();
        });

        test('should invoke resumeFocusState and removeEventListener on hidden event for unsupported modal', async () => {
            // simulate ARSystem.launch failure
            jest.spyOn(window.ARSystem, 'launch').mockRejectedValue(new Error('fail'));
            // prepare fake modal with capturable callback
            let capturedCb;
            const mockElement = {
                addEventListener: jest.fn((event, cb) => {
                    if (event === 'hidden.bs.modal') capturedCb = cb;
                }),
                removeEventListener: jest.fn(),
            };
            const mockModal = { _element: mockElement, show: jest.fn() };
            // override Modal constructor
            jest.spyOn(window.bootstrap, 'Modal').mockImplementation(() => mockModal);
            // call startARView
            await SpatialArViewerPluginObject.startARView();
            // ensure initial behaviors
            expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(mockModal.show).toHaveBeenCalled();
            expect(mockElement.addEventListener).toHaveBeenCalledWith('hidden.bs.modal', expect.any(Function));
            // simulate hidden event to cover resume and removal (lines 75-77)
            capturedCb();
            expect(window.focusHandler.resumeFocusState).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(mockElement.removeEventListener).toHaveBeenCalledWith('hidden.bs.modal', capturedCb);
        });

        test('does nothing when no ar-qr-modal exists (getARUnsupportedModalTemplate returns null)', async () => {
            // simulate ARSystem.launch failure
            jest.spyOn(window.ARSystem, 'launch').mockRejectedValue(new Error('fail'));
            // remove any ar-qr-modal element so template selection returns null
            document.body.innerHTML = `
                <div data-spatial-ar-viewer data-spatial-ar-viewer-options='{ "spatialArId":"1","modelUrl":"testurl" }'></div>
            `;
            // spy on focusHandler and Modal constructor
            const saveSpy = jest.spyOn(window.focusHandler, 'saveFocusState');
            const modalCtorSpy = jest.spyOn(window.bootstrap, 'Modal');
            // call startARView
            await SpatialArViewerPluginObject.startARView();
            // since no template, should return early: no focus saved, no Modal created
            expect(saveSpy).not.toHaveBeenCalled();
            expect(modalCtorSpy).not.toHaveBeenCalled();
        });
    });

    describe('onReady', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('does nothing if autostartAr param missing or mismatched', () => {
            window.location.search = '';
            window.autostartingARView = null;
            const spy = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            SpatialArViewerPluginObject.onReady();
            expect(window.autostartingARView).toBeNull();
            expect(spy).not.toHaveBeenCalled();
        });

        test('sets autostartingARView, shows autostart modal and binds click', () => {
            window.location.search = '?autostartAr=1';
            window.autostartingARView = null;
            const spy = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            const startSpy = jest.spyOn(SpatialArViewerPluginObject, 'startARView');

            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});
            SpatialArViewerPluginObject.onReady();
            expect(window.autostartingARView).toBe(true);
            expect(spy).toHaveBeenCalledWith('1');
            expect(modalShowSpy).toHaveBeenCalled();
            document.querySelector('.ar-btn-open-session').click();
            expect(startSpy).toHaveBeenCalled();
        });

        test('does not show autostart modal if no open-session element', () => {
            // remove open-session element to hit the early return in showARAutostartModal
            document.body.innerHTML = `
                <div data-spatial-ar-viewer
                    data-spatial-ar-viewer-options='{ "spatialArId": "1", "modelUrl": "testurl" }'>
                </div>
                <div class="ar-qr-modal">
                    <canvas data-ar-model-id="1"></canvas>
                </div>
            `;
            window.location.search = '?autostartAr=1';
            window.autostartingARView = null;
            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});
            SpatialArViewerPluginObject.onReady();
            expect(window.autostartingARView).toBe(true); // still toggles flag
            expect(modalShowSpy).not.toHaveBeenCalled();
        });

        test('does nothing when autostartingARView is not null (second if false)', () => {
            // valid autostartAr param and matching spatialArId
            window.location.search = '?autostartAr=1';
            // simulate already autostartingARView being not null (e.g., false)
            window.autostartingARView = false;
            const spy = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            SpatialArViewerPluginObject.onReady();
            // window.autostartingARView remains unchanged
            expect(window.autostartingARView).toBe(false);
            // autostart modal should not be shown again
            expect(spy).not.toHaveBeenCalled();
        });
    });
});
