/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import ContentSystemLayoutPresetApiService from 'src/core/service/api/content-system-layout-preset.api.service';
import type { ContentSystemLayoutPreset } from 'src/core/service/api/content-system-layout-preset.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function createService(): { service: ContentSystemLayoutPresetApiService; clientMock: MockAdapter } {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const service = new ContentSystemLayoutPresetApiService(client, loginService);

    return { service, clientMock };
}

describe('contentSystemLayoutPresetService', () => {
    it('is registered correctly', () => {
        const { service } = createService();

        expect(service).toBeInstanceOf(ContentSystemLayoutPresetApiService);
    });

    it('returns the presets from the info endpoint', async () => {
        const { service, clientMock } = createService();
        const presets: ContentSystemLayoutPreset[] = [
            { id: 'core.text-block', name: 'Text block', description: null, icon: 'regular-align-left', payload: [] },
        ];

        clientMock.onGet('/_info/content-system-layout-presets.json').reply(200, { presets });

        await expect(service.getPresets()).resolves.toEqual(presets);
    });

    it('returns an empty array when the response has no presets', async () => {
        const { service, clientMock } = createService();

        clientMock.onGet('/_info/content-system-layout-presets.json').reply(200, {});

        await expect(service.getPresets()).resolves.toEqual([]);
    });
});
