import ExtensionHelperService from 'src/app/service/extension-helper.service';

describe('src/app/service/extension-helper.service.js', () => {
    /**
     * @type ExtensionHelperService
     */
    let extensionHelperService;
    let extensionMock;

    beforeAll(() => {

    });

    beforeEach(() => {
        extensionHelperService = new ExtensionHelperService({
            storeService: {
                downloadPlugin: jest.fn(() => Promise.resolve())
            },
            pluginService: {
                install: jest.fn(() => Promise.resolve()),
                activate: jest.fn(() => Promise.resolve())
            },
            extensionApiService: {
                getMyExtensions: () => {
                    return Promise.resolve([
                        extensionMock
                    ]);
                }
            }
        });
    });

    [
        null,
        {
            name: 'SwagDummyExtension',
            installedAt: null,
            active: null
        },
        {
            name: 'SwagDummyExtension',
            installedAt: {
                date: '2021-01-02 09:59:46.324000'
            },
            active: null
        },
        {
            name: 'SwagDummyExtension',
            installedAt: {
                date: '2021-01-02 09:59:46.324000'
            },
            active: true
        }
    ].forEach((mock) => {
        // eslint-disable-next-line max-len
        it(`check installation with downloaded: ${!!mock}, installedAt: ${!!mock && !!mock.installedAt}, active: ${!!mock && !!mock.active}`, async () => {
            extensionMock = mock;

            await extensionHelperService.downloadAndActivateExtension('SwagDummyExtension');

            if (!mock) {
                expect(extensionHelperService.storeService.downloadPlugin).toHaveBeenCalled();
            } else {
                expect(extensionHelperService.storeService.downloadPlugin).not.toHaveBeenCalled();
            }

            if (!mock || !mock.installedAt) {
                expect(extensionHelperService.pluginService.install).toHaveBeenCalled();
            } else {
                expect(extensionHelperService.pluginService.install).not.toHaveBeenCalled();
            }

            if (!mock || !mock.active) {
                expect(extensionHelperService.pluginService.activate).toHaveBeenCalled();
            } else {
                expect(extensionHelperService.pluginService.activate).not.toHaveBeenCalled();
            }
        });
    });
});
