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
            global: { renderStubDefaultSlot: true },
        },
    );
}

async function showLoadedPreview(wrapper: Awaited<ReturnType<typeof createWrapper>>) {
    jest.advanceTimersByTime(800);
    await flushPromises();
    await wrapper.get('iframe').trigger('load');
}

async function changeLayout(wrapper: Awaited<ReturnType<typeof createWrapper>>) {
    await wrapper.setProps({ layout: { id: 'layout-a', name: 'Layout', layout: [{ id: 'element-1' }] } });
    jest.advanceTimersByTime(800);
    await flushPromises();
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

    it('reloads in the selected language and ignores the previous language response during the debounce', async () => {
        const previous = deferred<string>();
        request.mockReturnValueOnce(previous.promise);
        const assignLoadingFrame = jest.fn();
        wrapper = await createWrapper({ assignLoadingFrame });
        await wrapper.setProps({ languageId: 'system-language' });
        jest.advanceTimersByTime(800);
        await flushPromises();
        expect(request).toHaveBeenLastCalledWith(expect.objectContaining({ languageId: 'system-language' }));

        await wrapper.setProps({ languageId: 'german-language' });
        previous.resolve('https://storefront.local/old-language');
        await flushPromises();
        expect(assignLoadingFrame).not.toHaveBeenCalled();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(2);
        expect(request).toHaveBeenLastCalledWith(expect.objectContaining({ languageId: 'german-language' }));
        expect(assignLoadingFrame).toHaveBeenCalledWith('https://storefront.local/preview');
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

    it('shows an error instead of the preview when the first request fails', async () => {
        request.mockRejectedValue(new Error('HTTP 400'));
        wrapper = await createWrapper();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(wrapper.find('iframe').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-preview__outdated-banner').exists()).toBe(false);
        expect(wrapper.html()).toContain('sw-experience-studio.detail.preview.errorLoad');
    });

    it('keeps the earlier preview and marks it as outdated when an update fails', async () => {
        wrapper = await createWrapper();
        await showLoadedPreview(wrapper);
        expect(wrapper.find('.sw-experience-studio-preview__outdated-banner').exists()).toBe(false);

        request.mockRejectedValue(new Error('HTTP 500'));
        await changeLayout(wrapper);

        expect(request).toHaveBeenCalledTimes(2);
        expect(wrapper.get('iframe').attributes('src')).toBe('https://storefront.local/preview');
        expect(wrapper.get('.sw-experience-studio-preview__outdated-banner').text()).toContain(
            'sw-experience-studio.detail.preview.errorUpdate',
        );
    });

    it('keeps the outdated hint while the next request runs and removes it once a request succeeds', async () => {
        wrapper = await createWrapper();
        await showLoadedPreview(wrapper);
        request.mockRejectedValue(new Error('HTTP 500'));
        await changeLayout(wrapper);

        const pending = deferred<string>();
        request.mockReturnValue(pending.promise);
        await wrapper.get('.sw-experience-studio-preview__retry').trigger('click');
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(3);
        expect(wrapper.find('.sw-experience-studio-preview__outdated-banner').exists()).toBe(true);

        pending.resolve('https://storefront.local/updated-preview');
        await flushPromises();

        expect(wrapper.find('.sw-experience-studio-preview__outdated-banner').exists()).toBe(false);
        expect(wrapper.findAll('iframe').map((frame) => frame.attributes('src'))).toContain(
            'https://storefront.local/updated-preview',
        );
    });

    it('does not mark a current preview as outdated when an older request fails late', async () => {
        wrapper = await createWrapper();
        await showLoadedPreview(wrapper);

        let rejectOlderRequest!: (error: Error) => void;
        request.mockReturnValueOnce(
            new Promise<string>((_resolve, reject) => {
                rejectOlderRequest = reject;
            }),
        );
        await changeLayout(wrapper);

        request.mockResolvedValue('https://storefront.local/updated-preview');
        await wrapper.setProps({ entityId: 'other-product' });
        jest.advanceTimersByTime(800);
        await flushPromises();
        expect(request).toHaveBeenCalledTimes(3);

        rejectOlderRequest(new Error('HTTP 500'));
        await flushPromises();

        expect(wrapper.find('.sw-experience-studio-preview__outdated-banner').exists()).toBe(false);
    });

    it('drops a queued reload when the user retries', async () => {
        wrapper = await createWrapper();
        await showLoadedPreview(wrapper);
        request.mockRejectedValue(new Error('HTTP 500'));
        await changeLayout(wrapper);

        await wrapper.setProps({ entityId: 'other-product' });
        await wrapper.get('.sw-experience-studio-preview__retry').trigger('click');
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(3);
    });

    it('ignores the old preview while the next channel language context is loading', async () => {
        const previous = deferred<string>();
        request.mockReturnValueOnce(previous.promise);
        const assignLoadingFrame = jest.fn();
        wrapper = await createWrapper({ assignLoadingFrame });
        jest.advanceTimersByTime(800);
        await flushPromises();

        await wrapper.setProps({ salesChannelId: null });
        previous.resolve('https://storefront.local/old-channel');
        await flushPromises();
        expect(assignLoadingFrame).not.toHaveBeenCalled();

        await wrapper.setProps({ salesChannelId: 'next-channel' });
        jest.advanceTimersByTime(800);
        await flushPromises();
        expect(request).toHaveBeenLastCalledWith(expect.objectContaining({ salesChannelId: 'next-channel' }));
        expect(assignLoadingFrame).toHaveBeenCalledWith('https://storefront.local/preview');
    });
});
