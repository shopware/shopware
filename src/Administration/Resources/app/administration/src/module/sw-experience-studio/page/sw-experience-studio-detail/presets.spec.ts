import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail presets', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

    it('allows any preset at the root but gates slot presets by their root component', () => {
        const allowed = new Set(['Sw:Content:Text']);
        const rootPayload = { parentElementId: null, slotName: null, anchorTop: 0, anchorLeft: 0 };
        const slotPayload = { parentElementId: 'parent-1', slotName: 'content', anchorTop: 0, anchorLeft: 0 };

        const containerPreset = {
            id: 'p',
            name: 'P',
            description: null,
            icon: null,
            payload: [{ id: 'x', component: 'Sw:Grid:Container' }],
        };
        const textPreset = { ...containerPreset, payload: [{ id: 'x', component: 'Sw:Content:Text' }] };

        expect(methods.isPresetAllowedForPayload.call({}, containerPreset, rootPayload, allowed)).toBe(true);
        expect(methods.isPresetAllowedForPayload.call({}, containerPreset, slotPayload, allowed)).toBe(false);
        expect(methods.isPresetAllowedForPayload.call({}, textPreset, slotPayload, allowed)).toBe(true);
    });

    it('appends allowed presets to the picker elements and filters disallowed ones in slots', () => {
        const vm = {
            pendingAddElementPayload: { parentElementId: 'parent-1', slotName: 'content', anchorTop: 0, anchorLeft: 0 },
            getAvailableTypesForPayload: () => [{ name: 'Sw:Content:Text', label: 'Text', icon: 'i', category: 'content' }],
            layoutPresetStore: {
                allPresets: [
                    { id: 'allowed', name: 'Allowed', description: 'd', icon: 'p', payload: [{ id: 'a', component: 'Sw:Content:Text' }] },
                    { id: 'blocked', name: 'Blocked', description: null, icon: null, payload: [{ id: 'b', component: 'Sw:Grid:Container' }] },
                ],
            },
            isPresetAllowedForPayload: methods.isPresetAllowedForPayload,
        };

        const items = computed.availablePickerElements.call(vm) as Array<{ name: string; kind: string; id?: string }>;

        expect(items).toEqual([
            { name: 'Sw:Content:Text', label: 'Text', icon: 'i', category: 'content', kind: 'element' },
            { name: 'allowed', label: 'Allowed', icon: 'p', category: 'presets', kind: 'preset', id: 'allowed', description: 'd' },
        ]);
    });

    it('inserts a preset at the root through a single insert-preset mutation', async () => {
        const executeStructuralDraftMutation = jest.fn();
        const vm = {
            pendingAddElementPayload: { parentElementId: null, slotName: null, anchorTop: 0, anchorLeft: 0 },
            layout: { layout: [] },
            selectedElementId: 'existing',
            executeStructuralDraftMutation,
            onCloseElementPicker: jest.fn(),
        };

        await methods.onSelectPreset.call(vm, 'core.text-block');

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith('insert-preset', [], { presetId: 'core.text-block' }, expect.any(Function));
        expect(vm.onCloseElementPicker).toHaveBeenCalled();
    });

    it('passes the parent and slot when inserting a preset into a slot', async () => {
        const executeStructuralDraftMutation = jest.fn();
        const vm = {
            pendingAddElementPayload: { parentElementId: 'parent-1', slotName: 'content', anchorTop: 0, anchorLeft: 0 },
            layout: { layout: [] },
            selectedElementId: null,
            executeStructuralDraftMutation,
            onCloseElementPicker: jest.fn(),
        };

        await methods.onSelectPreset.call(vm, 'core.text-block');

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith(
            'insert-preset',
            [],
            { presetId: 'core.text-block', parentElementId: 'parent-1', slot: 'content' },
            expect.any(Function),
        );
    });
});
