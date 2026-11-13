import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import debounce from 'lodash/debounce';

const request = jest.fn<Promise<string>, []>();

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

async function createWrapper(methods: Record<string, jest.Mock> = {}) {
    return shallowMount(
        { extends: await wrapTestComponent('sw-experience-studio-preview', { sync: true }), methods } as Component,
        {
            props: {
                layout: { id: 'layout-a', name: 'Layout', layout: [] },
                salesChannelId: 'channel',
                entityType: 'product',
                entityId: 'product',
            },
        },
    );
}

describe('Experience Studio preview request lifecycle', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- Register the local preview service stub for this suite.
        Shopware.Service().register('contentSystemPreviewService', () => ({ previewEntityUrl: request }));
    });

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'setImmediate'] });
        jest.spyOn(Shopware.Utils, 'debounce').mockImplementation(debounce);
        request.mockReset().mockResolvedValue('https://storefront.local/preview');
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it('cancels a queued reload when the preview unmounts before the debounce expires', async () => {
        wrapper = await createWrapper();
        jest.advanceTimersByTime(799);
        expect(request).not.toHaveBeenCalled();

        wrapper.unmount();
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(request).not.toHaveBeenCalled();
    });

    it('loads the preview after the debounce while it remains mounted', async () => {
        wrapper = await createWrapper();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(1);
        expect(wrapper.get('iframe').attributes('src')).toBe('https://storefront.local/preview');
    });

    it('ignores a response that arrives after the preview unmounts', async () => {
        const pending = deferred<string>();
        request.mockReturnValue(pending.promise);
        const assignLoadingFrame = jest.fn();
        wrapper = await createWrapper({ assignLoadingFrame });
        jest.advanceTimersByTime(800);
        await flushPromises();
        expect(request).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        pending.resolve('https://storefront.local/late-preview');
        await flushPromises();

        expect(assignLoadingFrame).not.toHaveBeenCalled();
    });

    it('ignores a response when the preview unmounts while waiting for the scroll handshake', async () => {
        const scroll = deferred<{ top: number; left: number } | null>();
        const requestActiveFrameScrollPosition = jest.fn().mockReturnValue(scroll.promise);
        const assignLoadingFrame = jest.fn();
        wrapper = await createWrapper({ requestActiveFrameScrollPosition, assignLoadingFrame });
        jest.advanceTimersByTime(800);
        await flushPromises();
        expect(requestActiveFrameScrollPosition).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        scroll.resolve({ top: 100, left: 0 });
        await flushPromises();

        expect(assignLoadingFrame).not.toHaveBeenCalled();
    });
});
