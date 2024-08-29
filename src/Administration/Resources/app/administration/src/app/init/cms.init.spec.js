/**
 * @package content
 * @group disabledCompat
 */
import initCms from 'src/app/init/cms.init';
import 'src/module/sw-cms/service/cms.service';
import * as cms from '@shopware-ag/meteor-admin-sdk/es/ui/cms';
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
        await cms.registerCmsElement({
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
        await cms.registerCmsElement({
            name: 'test-element',
            defaultConfig: {},
            label: 'Test Element',
        });

        expect(mock).toHaveBeenCalledTimes(0);
    });

    it('should handle cmsRegisterBlock', async () => {
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

        Shopware.Service('cmsService').registerCmsBlock = mock;

        initCms();
        await cms.registerCmsBlock({
            name: 'test-block',
            label: 'Test Block',
            category: 'text',
            previewImage: 'https://placehold.co/600x400',
            slotLayout: {
                grid: 'auto / auto auto',
            },
            slots: [
                { element: 'test-element' },
            ],
        });

        expect(mock).toHaveBeenCalledWith({
            name: 'app-renderer',
            label: 'Test Block',
            category: 'text',
            component: 'sw-cms-block-app-renderer',
            previewComponent: 'sw-cms-block-app-preview-renderer',
            previewImage: 'https://placehold.co/600x400',
            appName: 'jestapp',
            slots: {
                'test-element-0': { type: 'test-element' },
            },
            defaultConfig: {
                customFields: {
                    appBlockName: 'test-block',
                    slotLayout: {
                        grid: 'auto / auto auto',
                    },
                },
            },
            // defaultConfig: [Object],
        });
    });

    it('should handle cmsRegisterBlock with fallback values', async () => {
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

        Shopware.Service('cmsService').registerCmsBlock = mock;

        initCms();
        await cms.registerCmsBlock({
            name: 'test-block',
            label: 'Test Block',
            slots: [
                { element: 'test-element' },
                { element: 'test-element' },
            ],
        });

        expect(mock).toHaveBeenCalledWith({
            name: 'app-renderer',
            label: 'Test Block',
            category: 'app',
            component: 'sw-cms-block-app-renderer',
            previewComponent: 'sw-cms-block-app-preview-renderer',
            previewImage: undefined,
            appName: 'jestapp',
            slots: {
                'test-element-0': { type: 'test-element' },
                'test-element-1': { type: 'test-element' },
            },
            defaultConfig: {
                customFields: {
                    appBlockName: 'test-block',
                    slotLayout: {
                        grid: undefined,
                    },
                },
            },
        });
    });

    it('should not handle cmsRegisterBlock if extension is not found', async () => {
        const mock = jest.fn();

        Shopware.Service('cmsService').registerCmsBlock = mock;

        initCms();
        await cms.registerCmsBlock({
            name: 'test-block',
            label: 'Test Block',
        });

        expect(mock).toHaveBeenCalledTimes(0);
    });
});
