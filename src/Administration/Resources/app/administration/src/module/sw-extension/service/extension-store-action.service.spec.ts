/**
 * @sw-package checkout
 */
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import ExtensionStoreActionService from './extension-store-action.service';

function createService() {
    const client = createHTTPClient(Shopware.Context.api);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    return {
        service: new ExtensionStoreActionService(client, loginService),
        clientMock,
    };
}

function requestedUrls(clientMock: MockAdapter): string[] {
    return clientMock.history.post.map((request) => request.url ?? '');
}

describe('src/module/sw-extension/service/extension-store-action.service', () => {
    it('requires the Composer package of a plugin before it installs the plugin', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost().reply(204);

        await service.installExtension('SwagExample', 'plugin');

        expect(requestedUrls(clientMock)).toEqual([
            '_action/extension/composer-require/plugin/SwagExample',
            '_action/extension/install/plugin/SwagExample',
        ]);
        expect(JSON.parse(clientMock.history.post[1].data as string)).toEqual({ separateComposerStep: true });
    });

    it('removes the Composer package again when the install fails', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost('_action/extension/install/plugin/SwagExample').reply(500);
        clientMock.onPost().reply(204);

        await expect(service.installExtension('SwagExample', 'plugin')).rejects.toThrow();

        expect(requestedUrls(clientMock)).toEqual([
            '_action/extension/composer-require/plugin/SwagExample',
            '_action/extension/install/plugin/SwagExample',
            '_action/extension/composer-remove/plugin/SwagExample',
        ]);
    });

    it('does not install a plugin whose Composer package could not be required', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost('_action/extension/composer-require/plugin/SwagExample').reply(400);
        clientMock.onPost().reply(204);

        await expect(service.installExtension('SwagExample', 'plugin')).rejects.toThrow();

        expect(requestedUrls(clientMock)).toEqual(['_action/extension/composer-require/plugin/SwagExample']);
    });

    it('runs Composer before and after the update of a plugin', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost().reply(204);

        await service.updateExtension('SwagExample', 'plugin', true);

        expect(requestedUrls(clientMock)).toEqual([
            '_action/extension/composer-require/plugin/SwagExample',
            '_action/extension/update/plugin/SwagExample',
            '_action/extension/composer-remove/plugin/SwagExample',
        ]);
        expect(JSON.parse(clientMock.history.post[1].data as string)).toEqual({
            allowNewPermissions: true,
            separateComposerStep: true,
        });
    });

    it('removes the Composer package of a plugin after its uninstall', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost().reply(204);

        await service.uninstallExtension('SwagExample', 'plugin', true);

        expect(requestedUrls(clientMock)).toEqual([
            '_action/extension/uninstall/plugin/SwagExample',
            '_action/extension/composer-remove/plugin/SwagExample',
        ]);
        expect(JSON.parse(clientMock.history.post[0].data as string)).toEqual({
            keepUserData: false,
            separateComposerStep: true,
        });
    });

    it('leaves Composer out for apps', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost().reply(204);

        await service.installExtension('SwagApp', 'app');
        await service.updateExtension('SwagApp', 'app');
        await service.uninstallExtension('SwagApp', 'app', false);

        expect(requestedUrls(clientMock)).toEqual([
            '_action/extension/install/app/SwagApp',
            '_action/extension/update/app/SwagApp',
            '_action/extension/uninstall/app/SwagApp',
        ]);
    });
});
