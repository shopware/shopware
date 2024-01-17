import initCms from 'src/app/init/cms.init';
import 'src/module/sw-cms/service/cms.service';
import { registerCmsElement } from '@shopware-ag/meteor-admin-sdk/es/ui/cms';
import extensionsStore from '../state/extensions.store';

describe('src/app/init/cms.init.ts', () => {
    beforeEach(() => {
        if (Shopware.State.get('extensions')) {
            Shopware.State.unregisterModule('extensions');
        }

        Shopware.State.registerModule('extensions', extensionsStore);
    });

    afterEach(() => {
        Shopware.State.unregisterModule('extensions');
    });

    it('should handle cmsRegisterElement', async () => {
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

        Shopware.Service('cmsService').registerCmsElement = mock;

        initCms();
        await registerCmsElement({
            name: 'test-element',
            defaultConfig: {},
            label: 'Test Element',
        });

        expect(mock).toHaveBeenCalledWith(expect.objectContaining({
            defaultConfig: expect.objectContaining({}),
            label: 'Test Element',
            name: 'test-element',
            component: 'sw-cms-el-location-renderer',
            previewComponent: 'sw-cms-el-preview-location-renderer',
            configComponent: 'sw-cms-el-config-location-renderer',
            appData: {
                baseUrl: '',
            },
        }));
    });

    it('should not handle cmsRegisterElement if extension is not found', async () => {
        const mock = jest.fn();

        Shopware.Service('cmsService').registerCmsElement = mock;

        initCms();
        await registerCmsElement({
            name: 'test-element',
            defaultConfig: {},
            label: 'Test Element',
        });

        expect(mock).toHaveBeenCalledTimes(0);
    });
});
