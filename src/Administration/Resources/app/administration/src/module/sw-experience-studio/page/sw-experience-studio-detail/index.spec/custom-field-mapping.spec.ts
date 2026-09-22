import detailComponent from '../index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail custom-field mapping', () => {
    const methods = (
        detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }
    ).methods;

    it('refreshes dynamic mapping candidates whenever the editor is entered', async () => {
        const loadMappingCandidates = jest.fn().mockResolvedValue(undefined);

        await methods.loadMappingCandidates.call({
            mappingCandidateStore: { loadMappingCandidates },
        });

        expect(loadMappingCandidates).toHaveBeenCalledWith(true);
    });
});
