import previewComponent from './index';

const methods = (previewComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

function createPreview() {
    const frameWindows = { a: { postMessage: jest.fn() }, b: { postMessage: jest.fn() } };
    const preview = {
        activeFrame: 'a' as 'a' | 'b' | null,
        loadingFrame: null as 'a' | 'b' | null,
        isPreviewLoading: false,
        pendingScrollElementId: null as string | null,
        pendingScrollPosition: null as { top: number; left: number } | null,
        getActiveFrameElement() {
            return this.activeFrame ? { contentWindow: frameWindows[this.activeFrame] } : null;
        },
        getActiveFrameOrigin: jest.fn((): string | null => 'https://storefront.local'),
        restoreFrameScrollPosition: jest.fn().mockResolvedValue(undefined),
        scrollToElement(elementId: string) {
            methods.scrollToElement.call(this, elementId);
        },
    };
    return { preview, frameWindows };
}

describe('preview component root scrolling', () => {
    it('sends scroll commands only to the active iframe and its origin, including repeated focus', () => {
        const { preview, frameWindows } = createPreview();
        preview.activeFrame = 'b';
        preview.scrollToElement('nested-text');
        preview.scrollToElement('nested-text');

        expect(frameWindows.b.postMessage).toHaveBeenCalledTimes(2);
        expect(frameWindows.b.postMessage).toHaveBeenLastCalledWith(
            { source: 'sw-experience-studio-admin', type: 'scroll-to-element', elementId: 'nested-text' },
            'https://storefront.local',
        );
        expect(frameWindows.a.postMessage).not.toHaveBeenCalled();
        expect(preview.pendingScrollElementId).toBeNull();
    });

    it('waits for the initial preview and uses the most recently focused component', async () => {
        const { preview, frameWindows } = createPreview();
        preview.activeFrame = null;
        preview.loadingFrame = 'a';
        preview.scrollToElement('first-text');
        preview.scrollToElement('nested-text');
        expect(frameWindows.a.postMessage).not.toHaveBeenCalled();

        await methods.onPreviewFrameLoad.call(preview, 'a');

        expect(frameWindows.a.postMessage).toHaveBeenCalledTimes(1);
        expect(frameWindows.a.postMessage).toHaveBeenCalledWith(
            expect.objectContaining({ elementId: 'nested-text' }),
            'https://storefront.local',
        );
        expect(preview.pendingScrollElementId).toBeNull();
    });

    it('reapplies focus after restoring the replacement preview scroll position', async () => {
        const { preview, frameWindows } = createPreview();
        preview.loadingFrame = 'b';
        preview.pendingScrollPosition = { top: 100, left: 0 };
        let finishRestore!: () => void;
        preview.restoreFrameScrollPosition.mockImplementationOnce(
            () =>
                new Promise<void>((resolve) => {
                    finishRestore = resolve;
                }),
        );
        preview.scrollToElement('first-text');
        const loaded = methods.onPreviewFrameLoad.call(preview, 'b');
        preview.scrollToElement('nested-text');
        expect(frameWindows.b.postMessage).not.toHaveBeenCalled();

        finishRestore();
        await loaded;

        expect(preview.restoreFrameScrollPosition).toHaveBeenCalledWith('b', { top: 100, left: 0 });
        expect(frameWindows.b.postMessage).toHaveBeenCalledWith(
            expect.objectContaining({ elementId: 'nested-text' }),
            'https://storefront.local',
        );
        expect(preview.activeFrame).toBe('b');
        expect(preview.pendingScrollElementId).toBeNull();
    });

    it('queues focus while a preview request is in progress', async () => {
        const { preview, frameWindows } = createPreview();
        preview.isPreviewLoading = true;
        preview.scrollToElement('text');
        expect(frameWindows.a.postMessage).toHaveBeenCalledTimes(1);
        expect(preview.pendingScrollElementId).toBe('text');

        preview.loadingFrame = 'b';
        preview.isPreviewLoading = false;
        await methods.onPreviewFrameLoad.call(preview, 'b');

        expect(frameWindows.b.postMessage).toHaveBeenCalledTimes(1);
        expect(preview.pendingScrollElementId).toBeNull();
    });

    it('keeps the focused component queued when a newer preview load runs after the frame loads', async () => {
        const { preview, frameWindows } = createPreview();
        preview.isPreviewLoading = true;
        preview.scrollToElement('text');
        preview.loadingFrame = 'b';

        await methods.onPreviewFrameLoad.call(preview, 'b');

        expect(frameWindows.b.postMessage).toHaveBeenCalledWith(
            expect.objectContaining({ elementId: 'text' }),
            'https://storefront.local',
        );
        expect(preview.pendingScrollElementId).toBe('text');
    });

    it('does not send a command without a known preview origin', () => {
        const { preview, frameWindows } = createPreview();
        preview.getActiveFrameOrigin.mockReturnValue(null);
        preview.scrollToElement('text');

        expect(frameWindows.a.postMessage).not.toHaveBeenCalled();
    });

    it('clears a queued component when the preview context is reset', () => {
        const { preview } = createPreview();
        preview.pendingScrollElementId = 'text';
        methods.resetPreviewFrames.call(preview);

        expect(preview.pendingScrollElementId).toBeNull();
    });
});
