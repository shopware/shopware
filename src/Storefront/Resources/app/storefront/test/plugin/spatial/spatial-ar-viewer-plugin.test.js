import SpatialArViewerPlugin from 'src/plugin/spatial/spatial-ar-viewer-plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');

const ARError = class {
    constructor(message) {
        this.message = message;
    }
};

const ARDesktopPlatformError = class extends ARError {
    constructor(message) {
        super(message);
    }
};

const ARQuickLookNotSafariError = class extends ARError {
    constructor(message) {
        super(message);
    }
};

const ARQuickLookVersionMismatchError = class extends ARError {
    constructor(message) {
        super(message);
    }
};

const ARQuickLookUnknownError = class extends ARError {
    constructor(message) {
        super(message);
    }
};


window.DIVEARPlugin = {
    ARSystem: class {
        launch = jest.fn().mockResolvedValue({});
    },
    ARError,
    ARDesktopPlatformError,
    ARQuickLookNotSafariError,
    ARQuickLookVersionMismatchError,
    ARQuickLookUnknownError,
};

jest.mock('@shopware-ag/dive/ar', () => ({
    ARSystem: jest.fn(),
    ARError,
    ARDesktopPlatformError,
}));

const arViewerOptions = {
    spatialArId: "1",
    modelUrl: "testurl",
    snippets: {
        arErrors: {
            desktopNotSupported: {
                reason: 'spatial.ar.errors.desktopNotSupported.reason',
                solution: 'spatial.ar.errors.desktopNotSupported.solution'
            },
            notSafariOnIOS: {
                reason: 'spatial.ar.errors.notSafariOnIOS.reason',
                solution: 'spatial.ar.errors.notSafariOnIOS.solution'
            },
            IOSVersionMismatch: {
                reason: 'spatial.ar.errors.IOSVersionMismatch.reason',
                solution: 'spatial.ar.errors.IOSVersionMismatch.solution'
            },
            unknownIOSError: {
                reason: 'spatial.ar.errors.unknownIOSError.reason',
                solution: 'spatial.ar.errors.unknownIOSError.solution'
            },
            unknownARError: {
                reason: 'spatial.ar.errors.unknownARError.reason',
                solution: 'spatial.ar.errors.unknownARError.solution'
            }
        },
        openArView: 'spatial.ar.openArView',
        launchingArView: 'spatial.ar.launchingArView',
    }
};

const arSystem = new window.DIVEARPlugin.ARSystem();

/**
 * @package innovation
 */
describe('SpatialArViewerPlugin', () => {
    let SpatialArViewerPluginObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <button class="ar-button">
                <span id="ar-button-text">${arViewerOptions.snippets.openArView}</span>
            </button>
            <div data-spatial-ar-viewer
                data-spatial-ar-viewer-options="${JSON.stringify(arViewerOptions)}">
            </div>
            <div class="ar-qr-modal">
                <canvas data-ar-model-id="1"></canvas>
                <span id="ar-qr-modal-error-reason">${arViewerOptions.snippets.arErrors.unknownARError.reason}</span>
                <span id="ar-qr-modal-error-solution">${arViewerOptions.snippets.arErrors.unknownARError.solution}</span>
                <div class="ar-qr-modal-open-session">
                    <button class="ar-btn-open-session" data-modal-open-ar-session-autostart="1">
                        <span id="ar-btn-open-session-text">${arViewerOptions.snippets.openArView}</span>
                    </button>
                </div>
            </div>
        `;

        window.autostartingARView = undefined;

        delete window.location;
        window.location = {
            ancestorOrigins: null,
            hash: null,
            host: 'test.com',
            port: '80',
            protocol: 'http:',
            hostname: 'test.com',
            href: 'http://test.com',
            origin: 'http://test.com',
            pathname: null,
            search: '',
            assign: null,
            reload: null,
            replace: null,
        };

        SpatialArViewerPluginObject = new SpatialArViewerPlugin(document.querySelector('[data-spatial-ar-viewer]'), {
            spatialArId: "1",
            modelUrl: "testurl"
        });
        SpatialArViewerPluginObject.arSystem = arSystem;
        SpatialArViewerPluginObject.options = arViewerOptions;
        SpatialArViewerPluginObject.launchingAR = false;
        SpatialArViewerPluginObject.qrModalAutostartAR = null;
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

    test('should call startARViewAsync when clicking the plugin element', async () => {
        const startARViewAsyncSpy = jest.spyOn(SpatialArViewerPluginObject, 'startARViewAsync');

        expect(startARViewAsyncSpy).not.toHaveBeenCalled();

        document.querySelector('[data-spatial-ar-viewer]').dispatchEvent(new Event('click'));

        expect(startARViewAsyncSpy).toHaveBeenCalled();
    });

    describe('startARViewAsync', () => {
        test('throws error if ARSystem is not loaded', async () => {
            SpatialArViewerPluginObject.arSystem = null;
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});
            await SpatialArViewerPluginObject.startARViewAsync();
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
            expect(consoleErrorSpy).toHaveBeenCalledWith(expect.any(String), expect.any(Error));
        });

        test('calls ARSystem.launch with correct args, set the ar button text, should not set the qrModalAutostartAR button text', async () => {
            expect(SpatialArViewerPluginObject.autostartArModalButton).toBeNull();

            const arButton = document.querySelector('.ar-button');
            expect(arButton).toBeDefined();

            SpatialArViewerPluginObject.el = arButton;

            // find the ar button text
            const arButtonText = arButton.querySelector('#ar-button-text');
            expect(arButtonText).toBeDefined();

            // we want to make sure this remains untouched while launching ar because we don't come from autostartAr!
            const autostartArModalButtonText = document.querySelector('.ar-qr-modal-open-session').querySelector('#ar-btn-open-session-text');
            expect(autostartArModalButtonText).toBeDefined();

            // create a promise for the arSystem.launch method that can be resolved by the test
            let resolveLaunchPromise;
            const launchPromise = new Promise((resolve) => {
                resolveLaunchPromise = resolve;
            });

            // make the ar launch return our custom promise
            const launchSpy = jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockResolvedValue(launchPromise);

            // check the ar button text is the default text
            expect(arButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(autostartArModalButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(SpatialArViewerPluginObject.launchingAR).toBe(false);

            // start the ar view
            const startARViewAsyncPromise = SpatialArViewerPluginObject.startARViewAsync();

            // check the ar button text while launching ar to be the launching text
            expect(arButtonText.textContent).toBe(arViewerOptions.snippets.launchingArView);

            // should NOT change the text of the autostartArModalButtonText
            expect(autostartArModalButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(SpatialArViewerPluginObject.launchingAR).toBe(true);

            // resolve the launch promise
            resolveLaunchPromise();

            await startARViewAsyncPromise;

            expect(arButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(autostartArModalButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(SpatialArViewerPluginObject.launchingAR).toBe(false);

            // check the ar button text is the default text after the launch promise is resolved
            expect(arButtonText.textContent).toBe(arViewerOptions.snippets.openArView);

            expect(launchSpy).toHaveBeenCalledWith(
                'testurl',
                expect.objectContaining({ arPlacement: 'horizontal', arScale: 'auto' })
            );
        });

        test('should not start AR view if already started (and .launchingAR is true)', async () => {
            // create a promise for the arSystem.launch method that can be resolved by the test
            let resolveLaunchPromise;
            const launchPromise = new Promise((resolve) => {
                resolveLaunchPromise = resolve;
            });

            // make the ar launch return our custom promise
            const launchSpy = jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockResolvedValue(launchPromise);

            SpatialArViewerPluginObject.launchingAR = true;
            expect(SpatialArViewerPluginObject.launchingAR).toBe(true);

            const startARViewAsyncPromise = SpatialArViewerPluginObject.startARViewAsync();

            // launchingAR should immediately be set to false because we run into finally block
            expect(SpatialArViewerPluginObject.launchingAR).toBe(false);

            // check the startARViewAsyncPromise is resolved
            expect(startARViewAsyncPromise).resolves.toBeUndefined();
            expect(launchSpy).not.toHaveBeenCalled();
        });

        test('on non-AR related launch error, prints error to console', async () => {
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new Error('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});
            await SpatialArViewerPluginObject.startARViewAsync();
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
        });

        test('on AR related launch error, if ARUnsupportedModalTemplate is not found, prints error to console', async () => {
            document.body.innerHTML = `
                <button class="ar-button">
                    <span id="ar-button-text">${arViewerOptions.snippets.openArView}</span>
                </button>
                <div data-spatial-ar-viewer
                    data-spatial-ar-viewer-options="${JSON.stringify(arViewerOptions)}">
                </div>
                <div class="ar-qr-modal-open-session">
                </div>
            `;

            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});
            const qrModal = document.querySelector('.ar-qr-modal');
            expect(qrModal).toBeNull();
            await SpatialArViewerPluginObject.startARViewAsync();
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
        });

        test('on AR related launch error, if ARUnsupportedModalTemplate is found, if reason or solution span is not found, prints error to console', async () => {
            document.body.innerHTML = `
                <button class="ar-button">
                    <span id="ar-button">${arViewerOptions.snippets.openArView}</span>
                </button>
                <div data-spatial-ar-viewer
                    data-spatial-ar-viewer-options="${JSON.stringify(arViewerOptions)}">
                </div>
                <div class="ar-qr-modal">
                    <canvas data-ar-model-id="1"></canvas>
                </div>
                <div class="ar-qr-modal-open-session">
                </div>
            `;

            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {}).mockImplementationOnce(() => {});
            const qrModal = document.querySelector('.ar-qr-modal');
            const errorReason = qrModal.querySelector('#ar-qr-modal-error-reason');
            const errorSolution = qrModal.querySelector('#ar-qr-modal-error-solution');
            expect(errorReason).toBeNull();
            expect(errorSolution).toBeNull();

            await SpatialArViewerPluginObject.startARViewAsync();

            expect(consoleErrorSpy).toHaveBeenCalledTimes(2);
            expect(consoleErrorSpy).toHaveBeenCalledWith(expect.stringContaining('#ar-qr-modal-error-reason not found in ARUnsupportedModalTemplate'));
            expect(consoleErrorSpy).toHaveBeenCalledWith(expect.stringContaining('#ar-qr-modal-error-solution not found in ARUnsupportedModalTemplate'));
        });

        test('on ARDesktopPlatformError, prints error to console as shows correct modal content', async () => {
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARDesktopPlatformError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            await SpatialArViewerPluginObject.startARViewAsync();

            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);

            const qrModal = SpatialArViewerPluginObject.getARUnsupportedModal('1');
            expect(qrModal).toBeDefined();

            const errorReason = qrModal.querySelector('#ar-qr-modal-error-reason');
            expect(errorReason).toBeDefined();
            expect(errorReason.textContent).toBe(arViewerOptions.snippets.arErrors.desktopNotSupported.reason);

            const errorSolution = qrModal.querySelector('#ar-qr-modal-error-solution');
            expect(errorSolution).toBeDefined();
            expect(errorSolution.textContent).toBe(arViewerOptions.snippets.arErrors.desktopNotSupported.solution);
        });

        test('on ARQuickLookNotSafariError, prints error to console as shows correct modal content', async () => {
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARQuickLookNotSafariError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            await SpatialArViewerPluginObject.startARViewAsync();

            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);

            const qrModal = SpatialArViewerPluginObject.getARUnsupportedModal('1');
            expect(qrModal).toBeDefined();

            const errorReason = qrModal.querySelector('#ar-qr-modal-error-reason');
            expect(errorReason).toBeDefined();
            expect(errorReason.textContent).toBe(arViewerOptions.snippets.arErrors.notSafariOnIOS.reason);

            const errorSolution = qrModal.querySelector('#ar-qr-modal-error-solution');
            expect(errorSolution).toBeDefined();
            expect(errorSolution.textContent).toBe(arViewerOptions.snippets.arErrors.notSafariOnIOS.solution);
        });

        test('on ARQuickLookVersionMismatchError, prints error to console as shows correct modal content', async () => {
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARQuickLookVersionMismatchError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            await SpatialArViewerPluginObject.startARViewAsync();

            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);

            const qrModal = SpatialArViewerPluginObject.getARUnsupportedModal('1');
            expect(qrModal).toBeDefined();

            const errorReason = qrModal.querySelector('#ar-qr-modal-error-reason');
            expect(errorReason).toBeDefined();
            expect(errorReason.textContent).toBe(arViewerOptions.snippets.arErrors.IOSVersionMismatch.reason);

            const errorSolution = qrModal.querySelector('#ar-qr-modal-error-solution');
            expect(errorSolution).toBeDefined();
            expect(errorSolution.textContent).toBe(arViewerOptions.snippets.arErrors.IOSVersionMismatch.solution);
        });

        test('on ARQuickLookUnknownError, prints error to console as shows correct modal content', async () => {
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARQuickLookUnknownError('fail'));
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            await SpatialArViewerPluginObject.startARViewAsync();

            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);

            const qrModal = SpatialArViewerPluginObject.getARUnsupportedModal('1');
            expect(qrModal).toBeDefined();

            const errorReason = qrModal.querySelector('#ar-qr-modal-error-reason');
            expect(errorReason).toBeDefined();
            expect(errorReason.textContent).toBe(arViewerOptions.snippets.arErrors.unknownIOSError.reason);

            const errorSolution = qrModal.querySelector('#ar-qr-modal-error-solution');
            expect(errorSolution).toBeDefined();
            expect(errorSolution.textContent).toBe(arViewerOptions.snippets.arErrors.unknownIOSError.solution);
        });

        test('on launch error without spatialArId, appends generic modal and shows', async () => {
            const focusSpy = jest.spyOn(window.focusHandler, 'saveFocusState');
            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            const plugin = new SpatialArViewerPlugin(
                document.querySelector('[data-spatial-ar-viewer]'),
                { spatialArId: null, modelUrl: 'testurl' }
            );
            plugin.arSystem = arSystem;
            plugin.options = {...arViewerOptions, spatialArId: null};

            jest.spyOn(plugin.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARError('fail'));

            await plugin.startARViewAsync();

            expect(focusSpy).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(modalShowSpy).toHaveBeenCalled();
            expect(consoleErrorSpy).toHaveBeenCalledTimes(1);
        });

        test('should invoke resumeFocusState and removeEventListener on hidden event for unsupported modal', async () => {
            // simulate ARSystem.launch failure
            jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockRejectedValue(new window.DIVEARPlugin.ARError('fail'));
            jest.spyOn(console, 'error').mockImplementationOnce(() => {});
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
            // call startARViewAsync
            await SpatialArViewerPluginObject.startARViewAsync();
            // ensure initial behaviors
            expect(window.focusHandler.saveFocusState).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(mockModal.show).toHaveBeenCalled();
            expect(mockElement.addEventListener).toHaveBeenCalledWith('hidden.bs.modal', expect.any(Function));
            // simulate hidden event to cover resume and removal (lines 75-77)
            capturedCb();
            expect(window.focusHandler.resumeFocusState).toHaveBeenCalledWith('spatial-ar-viewer');
            expect(mockElement.removeEventListener).toHaveBeenCalledWith('hidden.bs.modal', capturedCb);
        });
    });

    describe('autostartAr', () => {
        beforeEach(() => {
            jest.clearAllMocks();

            window.location.search = '?autostartAr=1';
            SpatialArViewerPluginObject.spatialArId = '1';
            window.autostartingARView = null;
        });

        test('should do nothing if no autostartAr param is present', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            window.location.search = '';

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBeNull();
            expect(spyShowARAutostartModal).not.toHaveBeenCalled();
        });

        test('should do nothing if no spatialArId is present', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            window.location.search = '?autostartAr=1';
            SpatialArViewerPluginObject.spatialArId = null;

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBeNull();
            expect(spyShowARAutostartModal).not.toHaveBeenCalled();
        });

        test('should do nothing if autostartAR is not equal to spatialArId', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            window.location.search = '?autostartAr=2';
            SpatialArViewerPluginObject.spatialArId = '1';

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBeNull();
            expect(spyShowARAutostartModal).not.toHaveBeenCalled();
        });

        test('should do nothing if autostartAR is not equal to spatialArId', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            window.location.search = '?autostartAr=2';
            SpatialArViewerPluginObject.spatialArId = '1';

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBeNull();
            expect(spyShowARAutostartModal).not.toHaveBeenCalled();
        });

        test('should not show autostart modal if autostartingARView is already true', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');

            window.location.search = '?autostartAr=1';
            SpatialArViewerPluginObject.spatialArId = '1';
            window.autostartingARView = true;

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBe(true);
            expect(spyShowARAutostartModal).not.toHaveBeenCalled();
        });

        test('should not show autostart modal if no open-session element is found', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            const spyModalShow = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});

            document.body.innerHTML = `
                <button class="ar-button">
                    <span id="ar-button-text">${arViewerOptions.snippets.openArView}</span>
                </button>
                <div data-spatial-ar-viewer
                    data-spatial-ar-viewer-options="${JSON.stringify(arViewerOptions)}">
                </div>
                <div class="ar-qr-modal">
                    <canvas data-ar-model-id="1"></canvas>
                    <span id="ar-qr-modal-error-reason">${arViewerOptions.snippets.arErrors.unknownARError.reason}</span>
                    <span id="ar-qr-modal-error-solution">${arViewerOptions.snippets.arErrors.unknownARError.solution}</span>
                    <!-- <div class="ar-qr-modal-open-session">
                        <button class="ar-btn-open-session" data-modal-open-ar-session-autostart="1">
                            <span id="ar-btn-open-session-text">${arViewerOptions.snippets.openArView}</span>
                        </button>
                    </div> -->
                </div>
            `;

            const qrModalAutostartModal = document.querySelector(
                `.ar-qr-modal-open-session`
            );
            expect(qrModalAutostartModal).toBeNull();

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBe(true);
            expect(spyShowARAutostartModal).toHaveBeenCalled();
            expect(SpatialArViewerPluginObject.autostartArModalButton).toBeNull();
            expect(spyModalShow).not.toHaveBeenCalled();
        });

        test('should not show autostart modal if no button in open-session element is found', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            const spyModalShow = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});

            document.body.innerHTML = `
                <button class="ar-button">
                    <span id="ar-button-text">${arViewerOptions.snippets.openArView}</span>
                </button>
                <div data-spatial-ar-viewer
                    data-spatial-ar-viewer-options="${JSON.stringify(arViewerOptions)}">
                </div>
                <div class="ar-qr-modal">
                    <canvas data-ar-model-id="1"></canvas>
                    <span id="ar-qr-modal-error-reason">${arViewerOptions.snippets.arErrors.unknownARError.reason}</span>
                    <span id="ar-qr-modal-error-solution">${arViewerOptions.snippets.arErrors.unknownARError.solution}</span>
                    <div class="ar-qr-modal-open-session">
                        <!-- button missing here -->
                    </div>
                </div>
            `;

            const qrModalAutostartModal = document.querySelector(
                `.ar-qr-modal-open-session`
            );
            expect(qrModalAutostartModal).toBeDefined();

            const qrModalAutostartModalArButton = qrModalAutostartModal.querySelector('[data-modal-open-ar-session-autostart="1"]');
            expect(qrModalAutostartModalArButton).toBeNull();

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBe(true);
            expect(spyShowARAutostartModal).toHaveBeenCalled();
            expect(SpatialArViewerPluginObject.autostartArModalButton).toBeNull();
            expect(spyModalShow).not.toHaveBeenCalled();
        });

        test('should show autostart modal if button in open-session element is found and bind click', () => {
            const spyShowARAutostartModal = jest.spyOn(SpatialArViewerPluginObject, 'showARAutostartModal');
            const spyModalShow = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});

            const qrModalAutostartModal = document.querySelector(
                `.ar-qr-modal-open-session`
            );
            expect(qrModalAutostartModal).toBeDefined();

            const qrModalAutostartModalArButton = qrModalAutostartModal.querySelector('[data-modal-open-ar-session-autostart="1"]');
            expect(qrModalAutostartModalArButton).toBeDefined();

            SpatialArViewerPluginObject.onReady();

            expect(window.autostartingARView).toBe(true);
            expect(spyShowARAutostartModal).toHaveBeenCalled();
            expect(SpatialArViewerPluginObject.autostartArModalButton).toBeDefined();
            expect(spyModalShow).toHaveBeenCalled();
        });

        test('should bind click if button in open-session element is found', () => {
            const spyStartARViewAsync = jest.spyOn(SpatialArViewerPluginObject, 'startARViewAsync');

            const qrModalAutostartModal = document.querySelector(
                `.ar-qr-modal-open-session`
            );
            expect(qrModalAutostartModal).toBeDefined();

            const qrModalAutostartModalArButton = qrModalAutostartModal.querySelector('[data-modal-open-ar-session-autostart="1"]');
            expect(qrModalAutostartModalArButton).toBeDefined();

            SpatialArViewerPluginObject.onReady();

            qrModalAutostartModalArButton.click();
            expect(spyStartARViewAsync).toHaveBeenCalled();
        });

        test('calls ARSystem.launch with correct args, set the ar button text, set the qrModalAutostartAR button text', async () => {
            const modalShowSpy = jest.spyOn(window.bootstrap.Modal.prototype, 'show').mockReturnValue({});

            const spyStartARViewAsync = jest.spyOn(SpatialArViewerPluginObject, 'startARViewAsync');

            // create a promise for the arSystem.launch method that can be resolved by the test
            let resolveLaunchPromise;
            const launchPromise = new Promise((resolve) => {
                resolveLaunchPromise = resolve;
            });

            // make the ar launch return our custom promise
            const launchSpy = jest.spyOn(SpatialArViewerPluginObject.arSystem, 'launch').mockResolvedValue(launchPromise);

            // make sure the autostartArModalButton is set
            const onReadyPromise = SpatialArViewerPluginObject.onReady();

            // make sure the autostartArModalButton is set
            expect(SpatialArViewerPluginObject.autostartArModalButton).not.toBeNull();

            // we want to make sure this remains untouched while launching ar because we don't come from autostartAr!
            const autostartArModalButtonText = SpatialArViewerPluginObject.autostartArModalButton.querySelector('#ar-btn-open-session-text');
            expect(autostartArModalButtonText).toBeDefined();

            // we start ar view automatically after the modal is shown
            expect(modalShowSpy).toHaveBeenCalled();
            expect(launchSpy).toHaveBeenCalled();

            // should change the text of the autostartArModalButtonText to the launching text
            expect(autostartArModalButtonText.textContent).toBe(arViewerOptions.snippets.launchingArView);
            expect(SpatialArViewerPluginObject.launchingAR).toBe(true);

            // resolve the launch promise
            resolveLaunchPromise();

            await onReadyPromise;

            expect(autostartArModalButtonText.textContent).toBe(arViewerOptions.snippets.openArView);
            expect(SpatialArViewerPluginObject.launchingAR).toBe(false);

            expect(launchSpy).toHaveBeenCalledWith(
                'testurl',
                expect.objectContaining({ arPlacement: 'horizontal', arScale: 'auto' })
            );
        });
    });
});
