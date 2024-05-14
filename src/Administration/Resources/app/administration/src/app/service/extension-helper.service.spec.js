import ExtensionHelperService from 'src/app/service/extension-helper.service';

/**
 * @package services-settings
 */
describe('src/app/service/extension-helper.service.js', () => {
    /**
     * @type ExtensionHelperService
     */
    let extensionHelperService;
    let extensionMock;

    beforeEach(async () => {
        extensionHelperService = new ExtensionHelperService({
            extensionStoreActionService: {
                getMyExtensions: () => {
                    return Promise.resolve([
                        extensionMock,
                    ]);
                },
                downloadExtension: jest.fn(() => Promise.resolve()),
                installExtension: jest.fn(() => Promise.resolve()),
                activateExtension: jest.fn(() => Promise.resolve()),
            },
        });
    });

    it('check installation with downloaded: false, installedAt: false, active: false', async () => {
        extensionMock = null;

        await extensionHelperService.downloadAndActivateExtension('SwagDummyExtension');

        expect(extensionHelperService.extensionStoreActionService.downloadExtension).toHaveBeenCalledTimes(1);
        expect(extensionHelperService.extensionStoreActionService.installExtension).toHaveBeenCalledTimes(1);
        expect(extensionHelperService.extensionStoreActionService.activateExtension).toHaveBeenCalledTimes(1);
    });

    it('check installation with downloaded: true, installedAt: false, active: false', async () => {
        extensionMock = {
            name: 'SwagDummyExtension',
            installedAt: null,
            active: null,
            source: 'local',
        };

        await extensionHelperService.downloadAndActivateExtension('SwagDummyExtension');

        expect(extensionHelperService.extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
        expect(extensionHelperService.extensionStoreActionService.installExtension).toHaveBeenCalledTimes(1);
        expect(extensionHelperService.extensionStoreActionService.activateExtension).toHaveBeenCalledTimes(1);
    });

    it('check installation with downloaded: true, installedAt: true, active: false', async () => {
        extensionMock = {
            name: 'SwagDummyExtension',
            installedAt: {
                date: '2021-01-02 09:59:46.324000',
            },
            active: null,
            source: 'local',
        };

        await extensionHelperService.downloadAndActivateExtension('SwagDummyExtension');

        expect(extensionHelperService.extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
        expect(extensionHelperService.extensionStoreActionService.installExtension).not.toHaveBeenCalled();
        expect(extensionHelperService.extensionStoreActionService.activateExtension).toHaveBeenCalledTimes(1);
    });

    it('check installation with downloaded: true, installedAt: true, active: true', async () => {
        extensionMock = {
            name: 'SwagDummyExtension',
            installedAt: {
                date: '2021-01-02 09:59:46.324000',
            },
            active: true,
            source: 'local',
        };

        await extensionHelperService.downloadAndActivateExtension('SwagDummyExtension');

        expect(extensionHelperService.extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
        expect(extensionHelperService.extensionStoreActionService.installExtension).not.toHaveBeenCalled();
        expect(extensionHelperService.extensionStoreActionService.activateExtension).not.toHaveBeenCalled();
    });
});
