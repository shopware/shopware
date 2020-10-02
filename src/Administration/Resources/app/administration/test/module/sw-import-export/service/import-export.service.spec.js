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
        }
    };

    return {
        importExportService: new ImportExportService(client, loginService),
        clientMock: clientMock
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

    it('should return the createdLog on export and track the progress ', async () => {
        const { importExportService, clientMock } = importExportServiceFactory();

        clientMock.onPost('/_action/import-export/prepare')
            .reply(200, {
                log: {
                    id: 'createdLogId'
                }
            });

        clientMock.onPost('/_action/import-export/process').reply((config) => {
            const data = JSON.parse(config.data);
            const total = 10;
            const offset = data.offset + 1;
            const state = offset === total ? 'succeeded' : 'progress';
            return [200, { progress: { logId: data.logId, total, offset, state } }];
        });

        const callback = jest.fn(progress => progress);
        const createdLog = await importExportService.export('profileId', callback);

        // Expect 10 calls, because total is 10 and offset increased by 1
        expect(callback).toHaveBeenCalledTimes(10);
        expect(callback).toHaveNthReturnedWith(1, { logId: 'createdLogId', offset: 1, state: 'progress', total: 10 });
        expect(callback).toHaveLastReturnedWith({ logId: 'createdLogId', offset: 10, state: 'succeeded', total: 10 });
        expect(createdLog.data).toEqual({ log: { id: 'createdLogId' } });
    });

    it('should return the createdLog on import and track the progress ', async () => {
        const { importExportService, clientMock } = importExportServiceFactory();

        clientMock.onPost('/_action/import-export/prepare')
            .reply(200, {
                log: {
                    id: 'createdLogId'
                }
            });

        clientMock.onPost('/_action/import-export/process').reply((config) => {
            const data = JSON.parse(config.data);
            const total = 10;
            const offset = data.offset + 1;
            const state = offset === total ? 'succeeded' : 'progress';
            return [200, { progress: { logId: data.logId, total, offset, state } }];
        });

        const callback = jest.fn(progress => progress);
        const createdLog = await importExportService.import('profileId', 'testFile', callback);

        // Expect 10 calls, because total is 10 and offset increased by 1
        expect(callback).toHaveBeenCalledTimes(10);
        expect(callback).toHaveNthReturnedWith(1, { logId: 'createdLogId', offset: 1, state: 'progress', total: 10 });
        expect(callback).toHaveLastReturnedWith({ logId: 'createdLogId', offset: 10, state: 'succeeded', total: 10 });
        expect(createdLog.data).toEqual({ log: { id: 'createdLogId' } });
    });
});
