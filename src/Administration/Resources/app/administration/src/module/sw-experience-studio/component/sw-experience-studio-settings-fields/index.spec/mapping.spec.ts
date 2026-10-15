import settingsFieldsComponent from '../index';

const computed = (
    settingsFieldsComponent as unknown as {
        computed: Record<string, (...args: unknown[]) => unknown>;
    }
).computed;
const methods = (
    settingsFieldsComponent as unknown as {
        methods: Record<string, (...args: unknown[]) => unknown>;
    }
).methods;

describe('data mapping', () => {
    const mappableTextField = {
        key: 'text',
        property: {
            type: 'string',
            contextTypes: ['single'],
            mappable: true,
            title: 'Text',
            adminUI: null,
        },
    };

    const staticTextField = {
        key: 'headline',
        property: {
            type: 'string',
            contextTypes: ['single'],
            mappable: false,
            title: 'Headline',
            adminUI: null,
        },
    };

    const mappableGalleryField = {
        key: 'mediaItems',
        property: {
            type: 'Shopware\\Core\\Content\\Media\\MediaCollection',
            contextTypes: ['collection'],
            mappable: true,
            title: 'Media',
            adminUI: null,
        },
    };

    const categoryNameCandidate = {
        path: 'category.name',
        source: { type: 'root', id: 'category', path: 'name' },
        label: 'sw-experience-studio.mapping.category.name.label',
        description: 'sw-experience-studio.mapping.category.name.description',
        group: 'basic',
        valueType: 'string',
        compatibleTypes: ['string'],
        inlineMappable: true,
        canonicalToken: '{{map:category.name}}',
        contextType: 'single',
    };

    const mediaCandidate = {
        path: 'category.media',
        source: { type: 'root', id: 'category', path: 'media' },
        label: 'sw-experience-studio.mapping.category.media.label',
        description: 'sw-experience-studio.mapping.category.media.description',
        group: 'media',
        valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity',
        compatibleTypes: [
            'Shopware\\Core\\Content\\Media\\MediaEntity',
            'object',
        ],
        inlineMappable: false,
        canonicalToken: '{{map:category.media}}',
        contextType: 'single',
    };

    it('offers only candidates that can fill a mappable property', () => {
        const candidates = methods.getMappingCandidatesForField.call(
            {
                mappingCandidates: [
                    categoryNameCandidate,
                    mediaCandidate,
                ],
            },
            mappableTextField,
        ) as Array<{ path: string }>;

        expect(candidates.map((candidate) => candidate.path)).toEqual(['category.name']);
    });

    // Both are class types, so the class check admits the single image for the gallery; only the
    // context type tells them apart, and picking the image rendered an empty gallery.
    it('offers no single-media candidate for a collection property', () => {
        const candidates = methods.getMappingCandidatesForField.call(
            {
                mappingCandidates: [
                    mediaCandidate,
                    {
                        ...mediaCandidate,
                        path: 'product.media',
                        valueType: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                        canonicalToken: '{{map:product.media}}',
                        compatibleTypes: [
                            'Shopware\\Core\\Content\\Media\\MediaCollection',
                            'object',
                        ],
                        contextType: 'collection',
                    },
                ],
            },
            mappableGalleryField,
        ) as Array<{ path: string }>;

        expect(candidates.map((candidate) => candidate.path)).toEqual(['product.media']);
    });

    it('offers nothing for a property that did not opt in', () => {
        const candidates = methods.getMappingCandidatesForField.call(
            {
                mappingCandidates: [categoryNameCandidate],
            },
            staticTextField,
        ) as unknown[];

        expect(candidates).toEqual([]);
    });

    it('routes an inlineMappable property to the inline text field, not the whole-field chip', () => {
        const inlineTextField = {
            key: 'text',
            property: {
                type: 'string',
                contextTypes: ['single'],
                mappable: false,
                inlineMappable: true,
                title: 'Text',
                adminUI: { component: 'text-editor' },
            },
        };

        expect(methods.isInlineMappableField.call({}, inlineTextField)).toBe(true);
        expect(methods.isInlineMappableField.call({}, mappableTextField)).toBe(false);
        expect(methods.isInlineMappableField.call({}, staticTextField)).toBe(false);

        // Nothing may be mapped onto it as a whole, so the map action never appears alongside the editor.
        expect(
            methods.canMapField.call(
                {
                    mappingCandidates: [categoryNameCandidate],
                    mappings: {},
                    getMappingCandidatesForField: methods.getMappingCandidatesForField,
                    isFieldMapped: methods.isFieldMapped,
                },
                inlineTextField,
            ),
        ).toBe(false);
    });

    it('offers only stringifiable candidates for inline mapping, regardless of property', () => {
        const candidates = computed.inlineMappingCandidates.call({
            mappingCandidates: [
                categoryNameCandidate,
                mediaCandidate,
            ],
        }) as Array<{ path: string }>;

        expect(candidates.map((candidate) => candidate.path)).toEqual(['category.name']);
    });

    it('hides the map action once the property is mapped', () => {
        const context = {
            mappingCandidates: [categoryNameCandidate],
            mappings: { text: 'category.name' },
            getMappingCandidatesForField: methods.getMappingCandidatesForField,
            isFieldMapped: methods.isFieldMapped,
        };

        expect(methods.canMapField.call(context, mappableTextField)).toBe(false);
        expect(methods.isFieldMapped.call(context, mappableTextField)).toBe(true);
    });

    it('hides the map action when the catalogue offers nothing usable', () => {
        const context = {
            mappingCandidates: [mediaCandidate],
            mappings: {},
            getMappingCandidatesForField: methods.getMappingCandidatesForField,
            isFieldMapped: methods.isFieldMapped,
        };

        expect(methods.canMapField.call(context, mappableTextField)).toBe(false);
    });

    it('labels a mapping with its snippet, and with the raw path when the catalogue lost it', () => {
        const context = {
            mappingCandidates: [categoryNameCandidate],
            mappings: {
                text: 'category.name',
                headline: 'product.name',
            },
            getFieldMappingPath: methods.getFieldMappingPath,
            $te: (key: string) => key === categoryNameCandidate.label,
            $t: () => 'Category name',
        };

        expect(methods.getFieldMappingLabel.call(context, mappableTextField)).toBe('Category name');
        expect(methods.getFieldMappingLabel.call(context, staticTextField)).toBe('product.name');
    });

    it('labels a custom-field mapping from its configured translations', () => {
        const customFieldCandidate = {
            ...categoryNameCandidate,
            path: 'product.customFields.material',
            label: 'material',
            labelTranslations: { custom: 'Material' },
            group: 'customFields',
        };
        const context = {
            mappingCandidates: [customFieldCandidate],
            mappings: {
                text: 'product.customFields.material',
            },
            getFieldMappingPath: methods.getFieldMappingPath,
            $te: () => false,
            $t: (key: string) => key,
        };

        expect(methods.getFieldMappingLabel.call(context, mappableTextField)).toBe('Material');
    });

    it('emits the chosen source together with its context type and closes the modal', () => {
        const emitted: unknown[] = [];
        const context = {
            allowEdit: true,
            mappingModalFieldKey: 'text',
            mappingModalField: mappableTextField,
            $emit: (event: string, payload: unknown) =>
                emitted.push([
                    event,
                    payload,
                ]),
        };

        methods.onSelectMapping.call(context, categoryNameCandidate);

        expect(context.mappingModalFieldKey).toBeNull();
        expect(emitted).toEqual([
            [
                'update-mapping',
                {
                    key: 'text',
                    source: categoryNameCandidate.source,
                    contextType: 'single',
                },
            ],
        ]);
    });

    it('emits a null source to unmap', () => {
        const emitted: unknown[] = [];

        methods.onUnmapField.call(
            {
                allowEdit: true,
                $emit: (event: string, payload: unknown) =>
                    emitted.push([
                        event,
                        payload,
                    ]),
            },
            mappableTextField,
        );

        expect(emitted).toEqual([
            [
                'update-mapping',
                {
                    key: 'text',
                    source: null,
                    contextType: null,
                },
            ],
        ]);
    });

    it('stays silent on a read-only layout', () => {
        const emitted: unknown[] = [];
        const context = {
            allowEdit: false,
            mappingModalFieldKey: null as string | null,
            mappingModalField: mappableTextField,
            $emit: (event: string, payload: unknown) =>
                emitted.push([
                    event,
                    payload,
                ]),
        };

        methods.onOpenMappingModal.call(context, mappableTextField);
        methods.onUnmapField.call(context, mappableTextField);
        methods.onSelectMapping.call(context, categoryNameCandidate);

        expect(context.mappingModalFieldKey).toBeNull();
        expect(emitted).toEqual([]);
    });
});
