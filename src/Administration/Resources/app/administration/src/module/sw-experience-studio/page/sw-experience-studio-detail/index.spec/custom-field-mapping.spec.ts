import detailComponent from '../index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail custom-field mapping', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('refreshes dynamic mapping candidates whenever the editor is entered', async () => {
        const loadMappingCandidates = jest.fn().mockResolvedValue(undefined);

        const vm = {
            mappingCandidateStore: { loadMappingCandidates },
            loadMappingCandidates: methods.loadMappingCandidates,
            loadLayout: jest.fn(),
            loadDefaultPreviewSalesChannel: jest.fn(),
            loadElementTypes: jest.fn(),
            loadStyleOptions: jest.fn(),
            loadLayoutPresets: jest.fn(),
            loadLayoutTypes: jest.fn(),
            onHistoryKeydown: jest.fn(),
            historyKeydownHandler: null,
        };
        jest.spyOn(Shopware.Store.get('adminMenu'), 'collapseSidebar').mockImplementation(() => {});
        (detailComponent as unknown as { created: () => void }).created.call(vm);
        await flushPromises();

        expect(loadMappingCandidates).toHaveBeenCalledWith(true);
    });
});
