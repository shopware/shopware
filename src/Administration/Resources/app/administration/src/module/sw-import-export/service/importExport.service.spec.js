/**
 * @package system-settings
 */
import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

const getClientMock = () => {
    const client = createHTTPClient({ apiResourcePath: 'testPath' });
    const clientMock = new MockAdapter(client);

    return { client, clientMock };
};

const importExportServiceFactory = () => {
    const { client, clientMock } = getClientMock();
    const loginService = {
        getToken() {
            return 'token';
        },
    };

    return {
        importExportService: new ImportExportService(client, loginService),
        clientMock: clientMock,
    };
};

describe('core/service/login.service.js', () => {
    it('should contain all public functions', async () => {
        const { importExportService } = importExportServiceFactory();

        expect(importExportService).toHaveProperty('export');
        expect(importExportService).toHaveProperty('import');
        expect(importExportService).toHaveProperty('getDownloadUrl');
        expect(importExportService).toHaveProperty('trackProgress');
    });

    it('should return the createdLog on export and start process', async () => {
        const { importExportService, clientMock } = importExportServiceFactory();

        clientMock.onPost('/_action/import-export/prepare')
            .reply(200, {
                log: {
                    id: 'createdLogId',
                },
            });

        clientMock.onPost('/_action/import-export/process').reply(() => {
            return [204];
        });

        const callback = jest.fn();
        const createdLog = await importExportService.export('profileId', callback);

        // Expect callback to have been called
        expect(callback).toHaveBeenCalledTimes(1);
        expect(createdLog.data).toEqual({ log: { id: 'createdLogId' } });
    });

    it('should return the createdLog on import and start process', async () => {
        const { importExportService, clientMock } = importExportServiceFactory();

        clientMock.onPost('/_action/import-export/prepare')
            .reply(200, {
                log: {
                    id: 'createdLogId',
                },
            });

        clientMock.onPost('/_action/import-export/process').reply(() => {
            return [204];
        });

        const callback = jest.fn();
        const createdLog = await importExportService.import('profileId', 'testFile', callback);

        // Expect callback to have been called
        expect(callback).toHaveBeenCalledTimes(1);
        expect(createdLog.data).toEqual({ log: { id: 'createdLogId' } });
    });
});
