import detailComponent from './index';
import type Criteria from 'src/core/data/criteria.data';

const component = detailComponent as unknown as {
    methods: Record<string, (...args: unknown[]) => unknown>;
    computed: Record<string, (...args: unknown[]) => unknown>;
};
const methods = component.methods;

function languageContext(salesChannelId: string, languageId: string) {
    return {
        salesChannelId,
        defaultLanguageId: languageId,
        languageIds: [languageId],
        languageNames: { [languageId]: languageId },
        languageParents: { [languageId]: null } as Record<string, string | null>,
    };
}

function createEditor() {
    return {
        previewSalesChannelId: 'next-channel',
        previewLanguageRequestSequence: 0,
        previewLanguageContext: languageContext('previous-channel', 'english'),
        languageParents: {} as Record<string, string | null>,
        frames: [{ id: 'frame', languageId: 'english' }],
        loadPreviewLanguageContext: jest.fn(),
        applyPreviewLanguageContext: methods.applyPreviewLanguageContext,
        scheduleWorkspaceSetup: jest.fn(),
        createNotificationError: jest.fn(),
        createNotificationWarning: jest.fn(),
        $t: (key: string) => key,
    };
}

describe('preview languages in the selected sales channel', () => {
    it('filters every frame selector by the preview sales channel and active languages', () => {
        const criteria = component.computed.languageCriteria.call({
            previewSalesChannelId: 'selected-channel',
            frames: [{ id: 'desktop' }, { id: 'mobile' }],
        }) as Record<string, Criteria>;

        for (const frameCriteria of Object.values(criteria)) {
            expect(frameCriteria.parse().filter).toEqual(
                expect.arrayContaining([
                    { type: 'equals', field: 'active', value: true },
                    { type: 'equals', field: 'salesChannels.id', value: 'selected-channel' },
                ]),
            );
        }
    });

    it('loads assigned active languages and uses the channel default instead of an unavailable system language', async () => {
        const get = jest.fn().mockResolvedValue({
            languageId: 'german',
            languages: [
                { id: 'french', name: 'French', parentId: null, active: true },
                { id: 'german', name: 'German', parentId: 'parent', active: true },
                { id: 'inactive', parentId: null, active: false },
            ],
        });

        const context = await methods.loadPreviewLanguageContext.call({ salesChannelRepository: { get } }, 'selected');

        expect(context).toEqual({
            salesChannelId: 'selected',
            defaultLanguageId: 'german',
            languageIds: ['french', 'german'],
            languageNames: { french: 'French', german: 'German' },
            languageParents: { french: null, german: 'parent' },
        });
        expect(get).toHaveBeenCalledWith('selected', Shopware.Context.api, expect.anything());
    });

    it.each(['unassigned', 'inactive'])('returns no preview context for a channel with %s languages', async (state) => {
        const get = jest.fn().mockResolvedValue({
            languageId: 'german',
            languages: state === 'unassigned' ? [] : [{ id: 'german', name: 'German', active: false }],
        });

        const context = await methods.loadPreviewLanguageContext.call({ salesChannelRepository: { get } }, 'selected');

        expect(context).toBeNull();
    });

    it('keeps supported frame choices and replaces unavailable saved or legacy choices with the channel default', () => {
        const editor = createEditor();
        const frames = [
            { id: 'supported', languageId: 'french' },
            { id: 'unavailable', languageId: 'english' },
            { id: 'legacy' },
        ];
        const context = {
            ...languageContext('next-channel', 'german'),
            languageIds: ['german', 'french'],
            languageParents: { german: 'parent', french: null },
        };

        expect(methods.applyPreviewLanguageContext.call(editor, context, frames)).toBe(true);
        expect(frames).toEqual([
            { id: 'supported', languageId: 'french' },
            { id: 'unavailable', languageId: 'german' },
            { id: 'legacy', languageId: 'german' },
        ]);
        expect(editor.languageParents.german).toBe('parent');
    });

    it('ignores a delayed language response for a previously selected sales channel', async () => {
        const editor = createEditor();
        let finish!: (value: ReturnType<typeof languageContext>) => void;
        editor.loadPreviewLanguageContext.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        );
        const first = methods.refreshPreviewLanguages.call(editor);
        editor.previewSalesChannelId = 'latest-channel';
        editor.loadPreviewLanguageContext.mockResolvedValueOnce(languageContext('latest-channel', 'french'));

        await methods.refreshPreviewLanguages.call(editor);
        finish(languageContext('next-channel', 'german'));
        await first;

        expect(editor.previewLanguageContext.salesChannelId).toBe('latest-channel');
        expect(editor.frames[0].languageId).toBe('french');
        expect(editor.scheduleWorkspaceSetup).toHaveBeenCalledTimes(1);
    });

    it('defaults legacy frames without reporting a missing reference when the system language is unassigned', () => {
        const editor = createEditor();
        const frames = [{ id: 'legacy' }];

        expect(methods.applyPreviewLanguageContext.call(editor, languageContext('channel', 'german'), frames)).toBe(false);
        expect(frames).toEqual([{ id: 'legacy', languageId: 'german' }]);
    });

    it('keeps the last working channel and frame languages if loading the next channel fails', async () => {
        const editor = createEditor();
        editor.loadPreviewLanguageContext.mockRejectedValue(new Error('network failure'));

        await methods.refreshPreviewLanguages.call(editor);

        expect(editor.previewSalesChannelId).toBe('previous-channel');
        expect(editor.frames[0].languageId).toBe('english');
        expect(editor.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('keeps the last working channel and warns if the next channel has no active languages', async () => {
        const editor = createEditor();
        editor.loadPreviewLanguageContext.mockResolvedValueOnce(null);

        await methods.refreshPreviewLanguages.call(editor);

        expect(editor.previewSalesChannelId).toBe('previous-channel');
        expect(editor.previewLanguageContext.salesChannelId).toBe('previous-channel');
        expect(editor.frames[0].languageId).toBe('english');
        expect(editor.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-experience-studio.workspace.manager.missingReferences',
        });
        expect(editor.createNotificationError).not.toHaveBeenCalled();
    });

    it('starts newly opened frames in the selected channel default language', () => {
        const frame = methods.createPreviewFrame.call(
            {
                previewSalesChannelId: 'channel',
                previewLanguageContext: languageContext('channel', 'german'),
            },
            'layout',
            'frame',
        );

        expect(frame).toEqual(expect.objectContaining({ languageId: 'german' }));
    });

    it.each(['unsupported', 'loading'])(
        'rejects a frame language change while it is %s in the selected context',
        (state) => {
            const editor = {
                ...createEditor(),
                previewLanguageContext: languageContext(state === 'loading' ? 'previous-channel' : 'next-channel', 'german'),
                activateFrame: jest.fn(),
            };
            methods.onFrameLanguageChange.call(editor, 'frame', state === 'loading' ? 'german' : 'unavailable');

            expect(editor.frames[0].languageId).toBe('english');
            expect(editor.activateFrame).not.toHaveBeenCalled();
        },
    );

    it('holds preview rendering while a new channel language context is loading', () => {
        const editor = createEditor();
        expect(component.computed.isPreviewLanguageContextReady.call(editor)).toBe(false);
        editor.previewLanguageContext = languageContext('next-channel', 'german');
        expect(component.computed.isPreviewLanguageContextReady.call(editor)).toBe(true);
    });
});
