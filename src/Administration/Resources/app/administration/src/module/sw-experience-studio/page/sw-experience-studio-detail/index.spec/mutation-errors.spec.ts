import detailComponent from '../index';

describe('Experience Studio detail mutation errors', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it.each([
        [
            'CONTENT_SYSTEM__PROPERTY_NOT_MAPPABLE',
            'sw-experience-studio.detail.mappingMutationErrors.propertyNotMappable',
        ],
        [
            'CONTENT_SYSTEM__UNKNOWN_MAPPING_PATH',
            'sw-experience-studio.detail.mappingMutationErrors.unknownMappingPath',
        ],
        [
            'CONTENT_SYSTEM__MAPPING_TYPE_MISMATCH',
            'sw-experience-studio.detail.mappingMutationErrors.mappingTypeMismatch',
        ],
        [
            'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION',
            'sw-experience-studio.detail.mappingMutationErrors.propertyAliasCollision',
        ],
        [
            'CONTENT_SYSTEM__INVALID_MAPPING_SOURCE',
            'sw-experience-studio.detail.mappingMutationErrors.invalidMappingSource',
        ],
        [
            'CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE',
            'sw-experience-studio.detail.mutationErrors.unknownRootSource',
        ],
        [
            'CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND',
            'sw-experience-studio.detail.mutationErrors.targetNotFound',
        ],
        [
            'CONTENT_SYSTEM__MUTATION_UNKNOWN_TYPE',
            'sw-experience-studio.detail.mutationErrors.unknownElementType',
        ],
        [
            'CONTENT_SYSTEM__MUTATION_CYCLE',
            'sw-experience-studio.detail.mutationErrors.invalidStructure',
        ],
        [
            'CONTENT_SYSTEM__MUTATION_SLOT_REQUIRED',
            'sw-experience-studio.detail.mutationErrors.invalidStructure',
        ],
        [
            'CONTENT_SYSTEM__MUTATION_INVALID_WRAP_TARGETS',
            'sw-experience-studio.detail.mutationErrors.invalidStructure',
        ],
        [
            'CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE',
            'sw-experience-studio.detail.mutationErrors.invalidStructure',
        ],
    ])('shows translated mutation guidance for %s', (code, snippetKey) => {
        const createNotificationError = jest.fn();
        const vm = {
            createNotificationError,
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        methods.notifyMutationError.call(vm, [code]);

        expect(vm.$t).toHaveBeenCalledWith(snippetKey);
        expect(createNotificationError).toHaveBeenCalledWith({
            message: `translated:${snippetKey}`,
        });
    });

    it.each([
        { codes: ['CONTENT_SYSTEM__UNRECOGNIZED'] },
        { codes: [] },
    ])('shows a translated fallback for unknown or missing codes: $codes', ({ codes }) => {
        const createNotificationError = jest.fn();
        const vm = {
            createNotificationError,
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        methods.notifyMutationError.call(vm, codes);

        expect(vm.$t).toHaveBeenCalledWith('sw-experience-studio.detail.mutationErrors.generic');
        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'translated:sw-experience-studio.detail.mutationErrors.generic',
        });
    });

    it('uses recognized guidance when the response also contains an unknown error', () => {
        const vm = {
            createNotificationError: jest.fn(),
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        methods.notifyMutationError.call(vm, [
            'CONTENT_SYSTEM__UNRECOGNIZED',
            'CONTENT_SYSTEM__INVALID_MAPPING_SOURCE',
        ]);

        expect(vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'translated:sw-experience-studio.detail.mappingMutationErrors.invalidMappingSource',
        });
    });

    it('asks for a missing layout source without sending a mutation or fabricating a server error', async () => {
        const vm = {
            layout: { layout: [] },
            resolveMutationRootSource: jest.fn().mockReturnValue(null),
            executeStructuralDraftMutation: jest.fn(),
            notifyMutationError: jest.fn(),
            createNotificationError: jest.fn(),
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        await methods.onElementMappingChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            source: { type: 'root', id: 'product', path: 'name' },
            contextType: 'single',
        });

        expect(vm.executeStructuralDraftMutation).not.toHaveBeenCalled();
        expect(vm.notifyMutationError).not.toHaveBeenCalled();
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'translated:sw-experience-studio.detail.mappingMutationErrors.missingRootSource',
        });
    });

    it('still allows removing a mapping when the layout has no source', async () => {
        const vm = {
            layout: { layout: [] },
            resolveMutationRootSource: jest.fn().mockReturnValue(null),
            executeStructuralDraftMutation: jest.fn().mockResolvedValue(undefined),
            createNotificationError: jest.fn(),
        };

        await methods.onElementMappingChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            source: null,
            contextType: null,
        });

        expect(vm.executeStructuralDraftMutation).toHaveBeenCalledWith(
            'unmap-property',
            { elementId: 'element-1', propertyKey: 'text' },
            expect.any(Function),
        );
        expect(vm.createNotificationError).not.toHaveBeenCalled();
    });

    it.each([
        [
            'map-property',
            'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION',
            'sw-experience-studio.detail.mappingMutationErrors.propertyAliasCollision',
        ],
        [
            'map-property',
            'CONTENT_SYSTEM__INVALID_MAPPING_SOURCE',
            'sw-experience-studio.detail.mappingMutationErrors.invalidMappingSource',
        ],
        [
            'map-property',
            'CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE',
            'sw-experience-studio.detail.mutationErrors.unknownRootSource',
        ],
        [
            'unmap-property',
            'CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND',
            'sw-experience-studio.detail.mutationErrors.targetNotFound',
        ],
    ])('reports a failed %s request with %s and preserves the draft', async (operation, code, snippetKey) => {
        const layout = [{ id: 'element-1', component: 'Sw:Content:Text' }];
        const vm = {
            layout: { layout },
            allowSave: true,
            mutationQueue: Promise.resolve(),
            pendingMutationCount: 0,
            isLoading: false,
            selectedElementId: 'element-1',
            editorStore: { pushToHistory: jest.fn() },
            requestDraftMutation: jest.fn().mockRejectedValue({
                response: { status: 400, data: { errors: [{ code }] } },
            }),
            extractMutationErrorCodes: methods.extractMutationErrorCodes,
            notifyMutationError: methods.notifyMutationError,
            createNotificationError: jest.fn(),
            $t: jest.fn((key: string) => `translated:${key}`),
        };

        await methods.executeStructuralDraftMutation.call(vm, operation, {}, () => 'element-1');
        await vm.mutationQueue;

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: `translated:${snippetKey}`,
        });
        expect(vm.layout.layout).toBe(layout);
        expect(vm.selectedElementId).toBe('element-1');
        expect(vm.editorStore.pushToHistory).not.toHaveBeenCalled();
        expect(vm.isLoading).toBe(false);
        expect(vm.pendingMutationCount).toBe(0);
    });
});
