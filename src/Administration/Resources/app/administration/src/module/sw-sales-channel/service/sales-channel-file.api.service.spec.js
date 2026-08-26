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
        expect(httpClient.get).toHaveBeenCalledWith('/_action/sales-channel-file/agentic/sales-channel-id', {
            headers: expect.objectContaining({
                Authorization: 'Bearer test-token',
                'Content-Type': 'application/json',
            }),
        });
    });

    it('loads detail data for a discovered file', async () => {
        const response = {
            data: {
                fileFamily: 'agentic',
                fileName: '.well-known/ucp.json',
                templates: [
                    {
                        twigNamespace: 'Framework',
                        templateContent: 'Core template',
                    },
                ],
            },
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
        const result = await service.detail('agentic', 'sales-channel-id', '.well-known/ucp.json');

        expect(result).toEqual(response);
        expect(httpClient.get).toHaveBeenCalledWith('/_action/sales-channel-file/agentic/sales-channel-id/detail', {
            headers: expect.objectContaining({
                Authorization: 'Bearer test-token',
                'Content-Type': 'application/json',
            }),
            params: {
                fileName: '.well-known/ucp.json',
            },
        });
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
});
