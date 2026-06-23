import previewComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-preview', () => {
    const methods = (previewComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const watchers = (previewComponent as unknown as { watch: Record<string, (...args: unknown[]) => unknown> }).watch;

    it('allows reload scheduling only when auto reload is not suspended', () => {
        const debouncedLoadPreview = jest.fn();

        methods.schedulePreviewReload.call({
            suspendAutoReload: true,
            debouncedLoadPreview,
        });
        expect(debouncedLoadPreview).not.toHaveBeenCalled();

        methods.schedulePreviewReload.call({
            suspendAutoReload: false,
            debouncedLoadPreview,
        });
        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('triggers a reload when suspend flag switches off', () => {
        const debouncedLoadPreview = jest.fn();

        watchers.suspendAutoReload.call({
            debouncedLoadPreview,
        }, false, true);

        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('validates preview origin and source frame', () => {
        const frameWindow = {};
        const event = {
            source: frameWindow,
            origin: 'https://storefront.local',
        } as MessageEvent;

        const trusted = methods.isTrustedPreviewMessage.call({
            getActiveFrameElement: () => ({ contentWindow: frameWindow }),
            getActiveFrameOrigin: () => 'https://storefront.local',
        }, event);
        expect(trusted).toBe(true);

        const untrusted = methods.isTrustedPreviewMessage.call({
            getActiveFrameElement: () => ({ contentWindow: frameWindow }),
            getActiveFrameOrigin: () => 'https://other.local',
        }, event);
        expect(untrusted).toBe(false);
    });
});
