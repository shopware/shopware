import initActions from 'src/app/init/actions.init';
import { actionExecute } from '@shopware-ag/admin-extension-sdk/es/app/action';
import ExtensionSdkService from '../../core/service/api/extension-sdk.service';
import extensionsStore from '../state/extensions.store';

describe('src/app/init/actions.init.ts', () => {
    beforeAll(() => {
        Shopware.Service().register('extensionSdkService', () => {
            return new ExtensionSdkService();
        });
    });

    beforeEach(() => {
        if (Shopware.State.get('extensions')) {
            Shopware.State.unregisterModule('extensions');
        }

        Shopware.State.registerModule('extensions', extensionsStore);
    });

    afterEach(() => {
        Shopware.State.unregisterModule('extensions');
    });

    it('should handle actionExecute', async () => {
        const appName = 'jestapp';
        const mock = jest.fn();

        Shopware.State.commit('extensions/addExtension', {
            name: appName,
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.Service('extensionSdkService').runAction = mock;

        initActions();
        await actionExecute({
            entity: 'customer',
            url: 'https://example.com',
            entityIds: ['123'],
        });

        expect(mock).toHaveBeenCalledWith(expect.objectContaining({
            url: 'https://example.com',
            entity: 'customer',
            action: expect.any(String),
            appName: appName,
        }), ['123']);
    });

    it('should not handle actionExecute if extension is not found', async () => {
        const mock = jest.fn();

        Shopware.Service('extensionSdkService').runAction = mock;

        initActions();

        await expect(
            actionExecute({
                entity: 'customer',
                url: 'https://example.com',
                entityIds: ['123'],
            }),
        ).rejects.toThrow('Could not find an extension with the given event origin ""');

        expect(mock).toHaveBeenCalledTimes(0);
    });
});
