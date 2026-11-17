import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail picker', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('opens the element picker anchored to the add trigger', () => {
        const anchorElement = document.createElement('button');
        const payload = {
            parentElementId: 'parent-1',
            slotName: 'content',
            anchorElement,
        };
        const vm = {
            pendingAddElementPayload: null as typeof payload | null,
            pickerAnchorElement: null as HTMLElement | null,
            isElementPickerOpen: false,
        };

        methods.onAddElement.call(vm, payload);

        expect(vm.pendingAddElementPayload).toBe(payload);
        expect(vm.pickerAnchorElement).toBe(anchorElement);
        expect(vm.isElementPickerOpen).toBe(true);
    });

    it('clears the picker anchor when the picker is closed', () => {
        const vm = {
            isElementPickerOpen: true,
            pendingAddElementPayload: {
                parentElementId: null,
                slotName: null,
                anchorElement: document.createElement('button'),
            },
            pickerAnchorElement: document.createElement('button'),
        };

        methods.onCloseElementPicker.call(vm);

        expect(vm.isElementPickerOpen).toBe(false);
        expect(vm.pendingAddElementPayload).toBeNull();
        expect(vm.pickerAnchorElement).toBeNull();
    });
});

describe('module/sw-experience-studio/page/sw-experience-studio-detail availablePickerElements root source filter', () => {
    type Methods = Record<string, (...args: unknown[]) => unknown>;

    const methods = (detailComponent as unknown as { methods: Methods }).methods;
    const computed = (detailComponent as unknown as { computed: Methods }).computed;

    const restrictedType = {
        name: 'Sw:Navigation:Breadcrumb',
        label: 'Breadcrumb',
        defaultBindingRootSources: ['product', 'category'],
    };
    const unrestrictedType = { name: 'Sw:Content:Text', label: 'Text', defaultBindingRootSources: null };
    const emptyListType = { name: 'Sw:Broken', label: 'Broken', defaultBindingRootSources: [] };
    const container = {
        name: 'Sw:Grid:Container',
        label: 'Container',
        defaultBindingRootSources: null,
        slots: [
            { name: 'restricted', maxElements: null, allowList: ['Sw:Navigation:Breadcrumb', 'Sw:Content:Text'] },
            { name: 'open', maxElements: null, allowList: [] },
        ],
    };
    const allTypes = [
        restrictedType,
        unrestrictedType,
        emptyListType,
        container,
    ];
    const restrictedPreset = {
        id: 'Sw:ProductDetailPage',
        name: 'Product detail page',
        description: null,
        defaultBindingRootSources: ['product', 'category'],
        payload: [{ id: 'a', component: 'Sw:Grid:Container' }],
    };
    const unrestrictedPreset = {
        id: 'core.text-block',
        name: 'Text block',
        description: null,
        defaultBindingRootSources: null,
        payload: [{ id: 'b', component: 'Sw:Grid:Container' }],
    };
    const rootPayload = { parentElementId: null, slotName: null };

    function createVm(
        rootSource: string | undefined | null,
        payload: Record<string, unknown>,
        presets: unknown[] = [restrictedPreset, unrestrictedPreset],
    ) {
        return {
            pendingAddElementPayload: { anchorElement: null, ...payload },
            elementTypeStore: {
                allTypes,
                getByName: (name: string) => allTypes.find((type) => type.name === name) ?? null,
            },
            layoutPresetStore: { allPresets: presets },
            layout:
                rootSource === null
                    ? null
                    : { rootSource, layout: [{ id: 'parent-1', component: 'Sw:Grid:Container', slots: {} }] },
            getAvailableTypesForPayload: methods.getAvailableTypesForPayload,
            getStructurallyAvailableTypesForPayload: methods.getStructurallyAvailableTypesForPayload,
            isPresetAllowedForPayload: methods.isPresetAllowedForPayload,
            isOfferedOnRootSource: methods.isOfferedOnRootSource,
            resolveMutationRootSource: methods.resolveMutationRootSource,
            getLayoutRootSource: methods.getLayoutRootSource,
        };
    }

    function pickerNames(vm: ReturnType<typeof createVm>): string[] {
        return (computed.availablePickerElements.call(vm) as Array<{ name: string }>).map((item) => item.name);
    }

    function slotTypeNames(vm: ReturnType<typeof createVm>): string[] {
        const types = methods.getAvailableTypesForPayload.call(vm, vm.pendingAddElementPayload) as Array<{
            name: string;
        }>;

        return types.map((type) => type.name);
    }

    it('offers restricted and unrestricted items at the root of a product layout', () => {
        const names = pickerNames(createVm('product', rootPayload));

        expect(names).not.toContain('Sw:Broken');
        expect(names).toEqual(
            expect.arrayContaining([
                'Sw:Navigation:Breadcrumb',
                'Sw:Content:Text',
                'Sw:ProductDetailPage',
                'core.text-block',
            ]),
        );
    });

    it.each([
        ['a landing page layout', 'landing_page'],
        ['a layout without a root source', undefined],
        ['an empty root source', ''],
        ['no layout', null],
    ])('hides every restricted item for %s', (_label, rootSource) => {
        const names = pickerNames(createVm(rootSource, rootPayload));

        expect(names).not.toContain('Sw:Navigation:Breadcrumb');
        expect(names).not.toContain('Sw:Broken');
        expect(names).not.toContain('Sw:ProductDetailPage');
        expect(names).toContain('Sw:Content:Text');
        expect(names).toContain('core.text-block');
    });

    it.each([
        ['landing_page', ['Sw:Content:Text']],
        ['product', ['Sw:Navigation:Breadcrumb', 'Sw:Content:Text']],
    ])('applies the allow list of a slot on a %s layout', (rootSource, expectedNames) => {
        const vm = createVm(rootSource, { parentElementId: 'parent-1', slotName: 'restricted' });

        expect(slotTypeNames(vm)).toEqual(expectedNames);
    });

    it.each(['open', 'undefined-slot'])('filters the types of the slot %s on a landing page layout', (slotName) => {
        const vm = createVm('landing_page', { parentElementId: 'parent-1', slotName });
        const names = slotTypeNames(vm);

        expect(names).not.toContain('Sw:Navigation:Breadcrumb');
        expect(names).not.toContain('Sw:Broken');
        expect(names).toContain('Sw:Content:Text');
    });

    it('offers a preset rooted at a restricted type in a slot when its own root sources allow it', () => {
        const wiredPreset = {
            id: 'plugin.breadcrumb',
            name: 'Breadcrumb',
            description: null,
            defaultBindingRootSources: null,
            payload: [{ id: 'c', component: 'Sw:Navigation:Breadcrumb' }],
        };
        const vm = createVm('landing_page', { parentElementId: 'parent-1', slotName: 'restricted' }, [wiredPreset]);
        const names = pickerNames(vm);

        expect(names).toContain('plugin.breadcrumb');
        expect(names).toContain('Sw:Content:Text');
        expect(names).not.toContain('Sw:Navigation:Breadcrumb');
    });
});
