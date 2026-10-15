import type { ContentElementNode } from 'src/core/service/content-element.types';
import previewComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-preview', () => {
    const methods = (previewComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('schedules a debounced reload', () => {
        const debouncedLoadPreview = jest.fn();

        methods.schedulePreviewReload.call({ debouncedLoadPreview });

        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('relays element selection but ignores inline edit messages from the preview', () => {
        const created = (previewComponent as unknown as { created: () => void }).created;
        const emit = jest.fn();
        const vm = {
            debouncedLoadPreview: null as (() => void) | null,
            previewMessageHandler: null as ((event: MessageEvent) => void) | null,
            isTrustedPreviewMessage: () => true,
            schedulePreviewReload: jest.fn(),
            $emit: emit,
        };

        created.call(vm);

        const dispatch = (data: unknown) => {
            vm.previewMessageHandler?.({ data } as MessageEvent);
        };

        dispatch({ source: 'sw-experience-studio-preview', type: 'select-element', elementId: 'element-1' });
        dispatch({ source: 'sw-experience-studio-preview', type: 'inline-edit-start', elementId: 'element-1' });
        dispatch({
            source: 'sw-experience-studio-preview',
            type: 'inline-edit-commit',
            elementId: 'element-1',
            value: '<p>Overwritten</p>',
        });

        expect(emit).toHaveBeenCalledTimes(1);
        expect(emit).toHaveBeenCalledWith('select-element', 'element-1');

        window.removeEventListener('message', vm.previewMessageHandler as EventListener);
    });

    it('validates preview origin and source frame', () => {
        const frameWindow = {};
        const event = {
            source: frameWindow,
            origin: 'https://storefront.local',
        } as MessageEvent;

        const trusted = methods.isTrustedPreviewMessage.call(
            {
                getActiveFrameElement: () => ({ contentWindow: frameWindow }),
                getActiveFrameOrigin: () => 'https://storefront.local',
            },
            event,
        );
        expect(trusted).toBe(true);

        const untrusted = methods.isTrustedPreviewMessage.call(
            {
                getActiveFrameElement: () => ({ contentWindow: frameWindow }),
                getActiveFrameOrigin: () => 'https://other.local',
            },
            event,
        );
        expect(untrusted).toBe(false);
    });

    it('captures current active frame scroll position', () => {
        const scrollPosition = methods.captureActiveFrameScrollPosition.call({
            getActiveFrameElement: () => ({
                contentWindow: {
                    scrollY: 240,
                    scrollX: 16,
                },
            }),
        });

        expect(scrollPosition).toEqual({
            top: 240,
            left: 16,
        });
    });

    it('restores scroll position before loading frame switch', async () => {
        const restoreFrameScrollPosition = jest.fn().mockResolvedValue(undefined);
        const vm = {
            loadingFrame: 'b',
            activeFrame: 'a',
            pendingScrollPosition: {
                top: 140,
                left: 0,
            },
            restoreFrameScrollPosition,
        };

        await methods.onPreviewFrameLoad.call(vm, 'b');

        expect(vm.activeFrame).toBe('b');
        expect(vm.loadingFrame).toBeNull();
        expect(vm.pendingScrollPosition).toBeNull();
        expect(restoreFrameScrollPosition).toHaveBeenCalledWith('b', {
            top: 140,
            left: 0,
        });
    });

    it('prefers direct scroll capture before message fallback', async () => {
        const captureActiveFrameScrollPosition = jest.fn().mockReturnValue({
            top: 99,
            left: 12,
        });

        const result = await methods.requestActiveFrameScrollPosition.call({
            captureActiveFrameScrollPosition,
        });

        expect(captureActiveFrameScrollPosition).toHaveBeenCalledTimes(1);
        expect(result).toEqual({
            top: 99,
            left: 12,
        });
    });

    it('carries attributed specification values into the preview request body', async () => {
        const requestBodies: { layout: ContentElementNode[] }[] = [];
        const previewEntityUrl = jest.fn((payload: { layout: ContentElementNode[] }) => {
            requestBodies.push(payload);

            return Promise.resolve('https://storefront.local/preview');
        });

        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('contentSystemPreviewService', () => ({
            previewEntityUrl,
        }));

        const element: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Content:Text',
            attributedSpecifications: {
                'Sw:Content:Text': 'SwagBlog',
                headline: 'SwagPromotion',
            },
        };

        const vm = {
            layout: {
                layout: [element],
            },
            salesChannelId: 'sales-channel-1',
            entityType: 'product',
            entityId: 'product-1',
            latestRequestId: 0,
            isPreviewLoading: false,
            previewLoadError: null as string | null,
            pendingScrollPosition: null,
            hasAnyPreviewFrame: false,
            resetPreviewFrames: jest.fn(),
            requestActiveFrameScrollPosition: jest.fn().mockResolvedValue(null),
            assignLoadingFrame: jest.fn(),
        };

        await methods.loadPreview.call(vm);

        expect(requestBodies).toHaveLength(1);
        expect(requestBodies[0].layout[0].attributedSpecifications).toEqual({
            'Sw:Content:Text': 'SwagBlog',
            headline: 'SwagPromotion',
        });
    });
});
