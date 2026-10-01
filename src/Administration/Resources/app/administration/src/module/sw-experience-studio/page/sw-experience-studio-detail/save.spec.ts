import type { ContentElementNode } from 'src/core/service/content-element.types';
import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail save', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('surfaces the API error detail and stops loading when the save is refused', async () => {
        const authoredLayout: ContentElementNode[] = [
            { id: 'element-1', component: 'Sw:Media:Image', properties: {} },
        ];
        const rejection = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'unfilled_required_input',
                            status: '400',
                            detail: 'Required property "media" is wired from "mediaId", which has no value.',
                        },
                    ],
                },
            },
        };
        const save = jest.fn().mockRejectedValue(rejection);
        const get = jest.fn();
        const $t = jest.fn((key: string, values?: Record<string, string>) => `${key}:${values?.detail ?? ''}`);
        const vm = {
            layout: { id: 'layout-1', name: 'Landing page', layout: authoredLayout },
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: { save, get },
            applyPreviewContextDefaults: jest.fn(),
            createNotificationSuccess: jest.fn(),
            createNotificationError: jest.fn(),
            notifySaveError: methods.notifySaveError,
            extractApiErrorDetail: methods.extractApiErrorDetail,
            $t,
            $router: { push: jest.fn() },
            isCreateMode: true,
            isLoading: false,
        };

        await methods.onSave.call(vm);

        expect(vm.isLoading).toBe(false);
        expect(get).not.toHaveBeenCalled();
        expect(vm.createNotificationSuccess).not.toHaveBeenCalled();
        expect(vm.$router.push).not.toHaveBeenCalled();
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message:
                'sw-experience-studio.detail.messageSaveErrorDetail:Required property "media" is wired from "mediaId", which has no value.',
        });
        expect(vm.layout.layout).toEqual(authoredLayout);
    });

    it('falls back to the generic save error when the rejection carries no API detail', async () => {
        const save = jest.fn().mockRejectedValue(new Error('network down'));
        const $t = jest.fn((key: string) => key);
        const vm = {
            layout: { id: 'layout-1', name: 'Landing page', layout: [] as ContentElementNode[] },
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: { save, get: jest.fn() },
            applyPreviewContextDefaults: jest.fn(),
            createNotificationSuccess: jest.fn(),
            createNotificationError: jest.fn(),
            notifySaveError: methods.notifySaveError,
            extractApiErrorDetail: methods.extractApiErrorDetail,
            $t,
            isCreateMode: false,
            isLoading: false,
        };

        await methods.onSave.call(vm);

        expect(vm.isLoading).toBe(false);
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageSaveError',
        });
    });

    it('keeps the successful save when only the reload fails', async () => {
        const authoredLayout: ContentElementNode[] = [
            { id: 'element-1', component: 'Sw:Media:Image', properties: {} },
        ];
        const save = jest.fn().mockResolvedValue(undefined);
        const get = jest.fn().mockRejectedValue(new Error('network down'));
        const $t = jest.fn((key: string) => key);
        const vm = {
            layout: { id: 'layout-1', name: 'Landing page', layout: authoredLayout },
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: { save, get },
            applyPreviewContextDefaults: jest.fn(),
            createNotificationSuccess: jest.fn(),
            createNotificationError: jest.fn(),
            notifySaveError: methods.notifySaveError,
            extractApiErrorDetail: methods.extractApiErrorDetail,
            $t,
            $router: { push: jest.fn() },
            isCreateMode: true,
            isLoading: false,
        };

        await methods.onSave.call(vm);

        expect(save).toHaveBeenCalledTimes(1);
        expect(vm.isLoading).toBe(false);
        expect(vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageSaved',
        });
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageReloadError',
        });
        expect(vm.applyPreviewContextDefaults).not.toHaveBeenCalled();
        expect(vm.layout.layout).toEqual(authoredLayout);
        expect(vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.experience.studio.detail',
            params: { id: 'layout-1' },
        });
    });

    it('adopts the server-canonical layout returned by the save reload without client-side re-normalization', async () => {
        // Authored client-side: style as a bare scalar, no seeded default, keys in author order.
        const authoredElement: ContentElementNode = {
            component: 'Sw:Filter:Panel',
            style: { 'col-span': 6 },
            id: 'element-1',
            properties: { visibleFilterCount: 5 },
        };
        // Server-canonical: `ElementStyleNormalizer::normalizeValue()` broadcasts a breakpoint-aware
        // scalar across every `Breakpoint::values()` entry, `LayoutDefaultSeeder::seedElement()` appends
        // the `showLayoutSwitch: true` default the type declares and the author left out, and
        // `StoredElement::jsonSerialize()` fixes the key order.
        const canonicalElement: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Filter:Panel',
            properties: { visibleFilterCount: 5, showLayoutSwitch: true },
            style: { 'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 } },
        };
        const reloadedLayout = {
            id: 'layout-1',
            name: 'Landing page',
            layout: [canonicalElement],
        };
        const save = jest.fn().mockResolvedValue(undefined);
        const get = jest.fn().mockResolvedValue(reloadedLayout);
        const vm = {
            layout: {
                id: 'layout-1',
                name: 'Landing page',
                layout: [authoredElement],
            } as unknown as typeof reloadedLayout,
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: { save, get },
            applyPreviewContextDefaults: jest.fn(),
            createNotificationSuccess: jest.fn(),
            $t: jest.fn().mockReturnValue('saved'),
            isCreateMode: false,
            isLoading: false,
        };

        await methods.onSave.call(vm);

        const saveCalls = save.mock.calls as unknown[][];

        expect(saveCalls[0][0]).toEqual({
            id: 'layout-1',
            name: 'Landing page',
            layout: [
                {
                    component: 'Sw:Filter:Panel',
                    style: { 'col-span': 6 },
                    id: 'element-1',
                    properties: { visibleFilterCount: 5 },
                },
            ],
        });
        expect(vm.layout).toBe(reloadedLayout);
        expect(vm.layout.layout[0]).toBe(canonicalElement);
        expect(Object.keys(vm.layout.layout[0])).toEqual([
            'id',
            'component',
            'properties',
            'style',
        ]);
        expect(vm.layout.layout[0].style).toEqual({
            'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 },
        });
        expect(vm.layout.layout[0].properties).toEqual({ visibleFilterCount: 5, showLayoutSwitch: true });
    });
});
