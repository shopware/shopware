/**
 * @sw-package discovery
 */

import SalesChannelFileApiService from './sales-channel-file.api.service';

describe('src/module/sw-sales-channel/service/sales-channel-file.api.service', () => {
    it('loads discovered files for a sales channel and file family', async () => {
        const response = {
            data: [
                {
                    fileFamily: 'agentic',
                    fileName: 'llms.txt',
                },
            ],
        };
        const httpClient = {
            get: jest.fn(async () => ({
                data: response,
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };

        const service = new SalesChannelFileApiService(httpClient, loginService);
        const result = await service.list('agentic', 'sales-channel-id');

        expect(result).toEqual(response);
        expect(httpClient.get).toHaveBeenCalledWith(
            '/_action/sales-channel-file/agentic/sales-channel-id',
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                    'Content-Type': 'application/json',
                }),
            },
        );
    });

    it('loads a preview for a file with unsaved template overrides', async () => {
        const response = {
            fileName: 'llms.txt',
            contentType: 'text/plain; charset=utf-8',
            content: '# Example',
        };
        const httpClient = {
            post: jest.fn(async () => ({
                data: response,
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };

        const service = new SalesChannelFileApiService(httpClient, loginService);
        const result = await service.preview('agentic', 'sales-channel-id', 'llms.txt', {
            Framework: 'custom content',
        });

        expect(result).toEqual(response);
        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/sales-channel-file/agentic/sales-channel-id/preview',
            {
                fileName: 'llms.txt',
                templateOverrides: {
                    Framework: 'custom content',
                },
            },
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                    'Content-Type': 'application/json',
                }),
            },
        );
    });

    it('patches an existing configuration when the file has already been configured', async () => {
        const httpClient = {
            patch: jest.fn(async () => ({
                data: null,
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };
        const service = new SalesChannelFileApiService(httpClient, loginService);
        const file = {
            configuration: {
                id: 'configured-file-id',
                enabled: true,
                templateOverrides: {
                    Framework: 'custom content',
                },
            },
        };

        const result = await service.saveConfiguration(file, 'sales-channel-id', false);

        expect(result).toEqual({
            id: 'configured-file-id',
            enabled: false,
            templateOverrides: {
                Framework: 'custom content',
            },
        });
        expect(httpClient.patch).toHaveBeenCalledWith(
            '/sales-channel-file/configured-file-id',
            {
                enabled: false,
                templateOverrides: {
                    Framework: 'custom content',
                },
            },
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            },
        );
    });

    it('patches custom template overrides for an existing configuration', async () => {
        const httpClient = {
            patch: jest.fn(async () => ({
                data: null,
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };
        const service = new SalesChannelFileApiService(httpClient, loginService);
        const file = {
            configuration: {
                id: 'configured-file-id',
                enabled: true,
                templateOverrides: {},
            },
        };

        const result = await service.saveConfiguration(file, 'sales-channel-id', true, {
            user_provided_content: 'Ask before checkout.',
        });

        expect(result.templateOverrides).toEqual({
            user_provided_content: 'Ask before checkout.',
        });
        expect(httpClient.patch).toHaveBeenCalledWith(
            '/sales-channel-file/configured-file-id',
            {
                enabled: true,
                templateOverrides: {
                    user_provided_content: 'Ask before checkout.',
                },
            },
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            },
        );
    });

    it('creates a configuration when enabling a discovered file for the first time', async () => {
        jest.spyOn(Shopware.Utils, 'createId').mockReturnValue('new-configuration-id');

        const httpClient = {
            post: jest.fn(async () => ({
                data: null,
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };
        const service = new SalesChannelFileApiService(httpClient, loginService);
        const file = {
            fileFamily: 'agentic',
            fileName: 'llms.txt',
            configuration: null,
        };

        const result = await service.saveConfiguration(file, 'sales-channel-id', true);

        expect(result).toEqual({
            id: 'new-configuration-id',
            salesChannelId: 'sales-channel-id',
            fileFamily: 'agentic',
            fileName: 'llms.txt',
            enabled: true,
            templateOverrides: {},
        });
        expect(httpClient.post).toHaveBeenCalledWith(
            '/sales-channel-file',
            result,
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            },
        );

        Shopware.Utils.createId.mockRestore();
    });
});
