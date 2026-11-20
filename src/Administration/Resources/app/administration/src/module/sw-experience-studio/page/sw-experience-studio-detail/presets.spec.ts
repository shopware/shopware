import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail presets', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

    const pluginImageTypes = [{ name: 'Plugin:Image', label: 'Image', labelSnippet: 'plugin.element' }];
    const textTypes = [
        {
            name: 'Sw:Content:Text',
            label: 'Text',
            icon: 'i',
            illustration: 'content-text',
            category: 'content',
            priority: 100,
        },
    ];

    it('localizes picker text while keeping element and preset identifiers', () => {
        const translations: Record<string, string> = {
            'plugin.element': 'Bild',
            'plugin.preset': 'Medien und Text',
            'plugin.description': 'Eine Vorlage',
        };
        const vm = {
            $te: (key: string) => key in translations,
            $t: (key: string) => translations[key],
            pendingAddElementPayload: { parentElementId: null, slotName: null },
            getAvailableTypesForPayload: () => pluginImageTypes,
            getStructurallyAvailableTypesForPayload: () => pluginImageTypes,
            layoutPresetStore: {
                allPresets: [
                    {
                        id: 'plugin.preset',
                        name: 'Media and text',
                        nameSnippet: 'plugin.preset',
                        description: 'A preset',
                        descriptionSnippet: 'plugin.description',
                        payload: [],
                        defaultBindingRootSources: null,
                    },
                ],
            },
            isPresetAllowedForPayload: () => true,
        };
        const items = computed.availablePickerElements.call(vm) as Array<Record<string, unknown>>;

        expect(items[0]).toMatchObject({ name: 'Plugin:Image', label: 'Bild' });
        expect(items[1]).toMatchObject({ name: 'plugin.preset', label: 'Medien und Text', description: 'Eine Vorlage' });
        delete translations['plugin.preset'];
        expect((computed.availablePickerElements.call(vm) as Array<Record<string, unknown>>)[1].label).toBe(
            'Media and text',
        );
    });

    it('allows any preset at the root but gates slot presets by their root component', () => {
        const allowed = new Set(['Sw:Content:Text']);
        const unrestrictedContext = { isOfferedOnRootSource: methods.isOfferedOnRootSource };
        const rootPayload = { parentElementId: null, slotName: null, anchorElement: null };
        const slotPayload = { parentElementId: 'parent-1', slotName: 'content', anchorElement: null };

        const containerPreset = {
            id: 'p',
            name: 'P',
            description: null,
            icon: null,
            defaultBindingRootSources: null,
            payload: [{ id: 'x', component: 'Sw:Grid:Container' }],
        };
        const textPreset = { ...containerPreset, payload: [{ id: 'x', component: 'Sw:Content:Text' }] };

        expect(methods.isPresetAllowedForPayload.call(unrestrictedContext, containerPreset, rootPayload, allowed)).toBe(
            true,
        );
        expect(methods.isPresetAllowedForPayload.call(unrestrictedContext, containerPreset, slotPayload, allowed)).toBe(
            false,
        );
        expect(methods.isPresetAllowedForPayload.call(unrestrictedContext, textPreset, slotPayload, allowed)).toBe(true);
    });

    describe('root source applicability of the default bindings', () => {
        const rootPayload = { parentElementId: null, slotName: null, anchorElement: null };
        const slotPayload = { parentElementId: 'parent-1', slotName: 'content', anchorElement: null };
        const allowed = new Set(['Sw:Grid:Container']);
        const restrictedPreset = {
            id: 'Sw:ProductDetailPage',
            name: 'Product detail page',
            description: null,
            icon: null,
            defaultBindingRootSources: ['product', 'category'],
            payload: [
                {
                    id: 'container',
                    component: 'Sw:Grid:Container',
                    slots: { content: [{ id: 'breadcrumb', component: 'Sw:Navigation:Breadcrumb' }] },
                },
            ],
        };

        function createContext(rootSource: string | null) {
            return {
                layout: rootSource === null ? null : { rootSource, layout: [] },
                isOfferedOnRootSource: methods.isOfferedOnRootSource,
                resolveMutationRootSource: methods.resolveMutationRootSource,
                getLayoutRootSource: methods.getLayoutRootSource,
            };
        }

        it.each([
            [
                'landing_page',
                'root',
                rootPayload,
                false,
            ],
            [
                'landing_page',
                'slot',
                slotPayload,
                false,
            ],
            [
                'category',
                'root',
                rootPayload,
                true,
            ],
            [
                'category',
                'slot',
                slotPayload,
                true,
            ],
            [
                null,
                'root',
                rootPayload,
                false,
            ],
        ])(
            'on a layout with root source %s, a restricted preset for a %s payload is allowed: %s',
            (rootSource, _label, payload, expected) => {
                expect(
                    methods.isPresetAllowedForPayload.call(createContext(rootSource), restrictedPreset, payload, allowed),
                ).toBe(expected);
            },
        );

        it('rejects a preset with an empty list on a layout with a root source', () => {
            expect(
                methods.isPresetAllowedForPayload.call(
                    createContext('category'),
                    { ...restrictedPreset, defaultBindingRootSources: [] },
                    rootPayload,
                    allowed,
                ),
            ).toBe(false);
        });
    });

    it('appends allowed presets to the picker elements and filters disallowed ones in slots', () => {
        const vm = {
            pendingAddElementPayload: { parentElementId: 'parent-1', slotName: 'content', anchorElement: null },
            getStructurallyAvailableTypesForPayload: () => textTypes,
            layoutPresetStore: {
                allPresets: [
                    {
                        id: 'allowed',
                        name: 'Allowed',
                        description: 'd',
                        icon: 'p',
                        illustration: 'preset-media-and-text',
                        category: 'content',
                        priority: 200,
                        defaultBindingRootSources: null,
                        payload: [{ id: 'a', component: 'Sw:Content:Text' }],
                    },
                    {
                        id: 'blocked',
                        name: 'Blocked',
                        description: null,
                        icon: null,
                        defaultBindingRootSources: null,
                        payload: [{ id: 'b', component: 'Sw:Grid:Container' }],
                    },
                ],
            },
            getAvailableTypesForPayload: () => textTypes,
            isPresetAllowedForPayload: methods.isPresetAllowedForPayload,
            isOfferedOnRootSource: methods.isOfferedOnRootSource,
        };

        const items = computed.availablePickerElements.call(vm) as Array<{ name: string; kind: string; id?: string }>;

        expect(items).toEqual([
            {
                name: 'Sw:Content:Text',
                label: 'Text',
                icon: 'i',
                illustration: 'content-text',
                category: 'content',
                priority: 100,
                kind: 'element',
            },
            {
                name: 'allowed',
                label: 'Allowed',
                icon: 'p',
                illustration: 'preset-media-and-text',
                category: 'content',
                priority: 200,
                kind: 'preset',
                id: 'allowed',
                description: 'd',
            },
        ]);
    });

    it('inserts a preset at the root through a single insert-preset mutation', async () => {
        const executeStructuralDraftMutation = jest.fn();
        const vm = {
            pendingAddElementPayload: { parentElementId: null, slotName: null, anchorElement: null },
            layout: { layout: [] },
            selectedElementId: 'existing',
            executeStructuralDraftMutation,
            onCloseElementPicker: jest.fn(),
        };

        await methods.onSelectPreset.call(vm, 'core.text-block');

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith(
            'insert-preset',
            { presetId: 'core.text-block' },
            expect.any(Function),
        );
        expect(vm.onCloseElementPicker).toHaveBeenCalled();
    });

    it('passes the parent and slot when inserting a preset into a slot', async () => {
        const executeStructuralDraftMutation = jest.fn();
        const vm = {
            pendingAddElementPayload: { parentElementId: 'parent-1', slotName: 'content', anchorElement: null },
            layout: { layout: [] },
            selectedElementId: null,
            executeStructuralDraftMutation,
            onCloseElementPicker: jest.fn(),
        };

        await methods.onSelectPreset.call(vm, 'core.text-block');

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith(
            'insert-preset',
            { presetId: 'core.text-block', parentElementId: 'parent-1', slot: 'content' },
            expect.any(Function),
        );
    });
});
