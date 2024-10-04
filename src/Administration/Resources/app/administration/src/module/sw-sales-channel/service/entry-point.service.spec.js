/**
 * @package buyers-experience
 */

import EntryPointService from 'src/module/sw-sales-channel/service/entry-point.service';

const token = 'fce2c5c0-518c-4f16-b893-4f0913c07efe';

describe('module/sw-sales-channel/service/entry-point.service.spec.js', () => {
    let service;

    beforeEach(async () => {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({
                data: [{
                    additional: '51b8d69c91144798b7c59c5fd26a53c2',
                }],
            })),
        };

        const loginService = {
            getToken: jest.fn(() => token),
        };

        service = new EntryPointService(httpClient, loginService);
    });

    it('list > should return list of custom entrypoints', async () => {
        expect(service.list()).toEqual([{ additional: '51b8d69c91144798b7c59c5fd26a53c2' }]);
    });
});
