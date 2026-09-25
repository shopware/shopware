import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import {
    findPropertyMapping,
    getCandidatesForProperty,
    getInlineMappingCandidates,
    getMappingCandidateTranslation,
    groupCandidates,
    isInlineMappableProperty,
    isMappableProperty,
} from './element-mapping.util';

describe('getMappingCandidateTranslation', () => {
    it('uses current locale, fallback locale, and then the first configured translation', () => {
        const translations = {
            'de-DE': 'Material',
            'en-GB': 'Material',
        };

        expect(getMappingCandidateTranslation(translations, 'de-DE', 'en-GB')).toBe('Material');
        expect(getMappingCandidateTranslation(translations, 'fr-FR', 'en-GB')).toBe('Material');
        expect(getMappingCandidateTranslation({ 'de-DE': '', 'en-GB': 'Material' }, 'de-DE', 'en-GB')).toBe('Material');
        expect(getMappingCandidateTranslation({ 'it-IT': 'Materiale' }, 'fr-FR', 'en-GB')).toBe('Materiale');
        expect(getMappingCandidateTranslation(undefined, 'de-DE', 'en-GB')).toBe('');
    });
});

function property(overrides: Partial<ContentSystemElementTypeProperty> = {}): ContentSystemElementTypeProperty {
    return {
        type: 'string',
        contextTypes: ['single'],
        translatable: false,
        enum: null,
        default: null,
        required: false,
        mappable: false,
        inlineMappable: false,
        title: 'Text',
        description: '',
        adminUI: null,
        ...overrides,
    };
}

function candidate(overrides: Partial<ContentSystemMappingCandidate> = {}): ContentSystemMappingCandidate {
    return {
        path: 'category.name',
        label: 'sw-experience-studio.mapping.category.name.label',
        description: 'sw-experience-studio.mapping.category.name.description',
        group: 'basic',
        valueType: 'string',
        contextType: 'single',
        projection: null,
        ...overrides,
    };
}

describe('module/sw-experience-studio/util/element-mapping.util', () => {
    describe('isMappableProperty', () => {
        it('admits only a property that opts in', () => {
            expect(isMappableProperty(property({ mappable: true }))).toBe(true);
            expect(isMappableProperty(property({ mappable: false }))).toBe(false);
        });
    });

    describe('isInlineMappableProperty', () => {
        it('admits only a property that opts in', () => {
            expect(isInlineMappableProperty(property({ inlineMappable: true }))).toBe(true);
            expect(isInlineMappableProperty(property({ inlineMappable: false }))).toBe(false);
        });
    });

    describe('getInlineMappingCandidates', () => {
        it('keeps only the candidates that have a text form', () => {
            const candidates = [
                candidate({ path: 'product.name', valueType: 'string' }),
                candidate({ path: 'product.stock', valueType: 'integer' }),
                candidate({ path: 'product.price', valueType: 'number' }),
                candidate({ path: 'product.active', valueType: 'boolean' }),
                candidate({ path: 'product.cover', valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity' }),
                candidate({ path: 'product.media', valueType: 'Shopware\\Core\\Content\\Media\\MediaCollection' }),
            ];

            expect(getInlineMappingCandidates(candidates).map((entry) => entry.path)).toEqual([
                'product.name',
                'product.stock',
                'product.price',
                'product.active',
            ]);
        });

        /**
         * `valueType` is the effective type after any projection, so a projection that formats an entity as text is
         * offered on its result rather than on the entity it reads.
         */
        it('offers a projected candidate on its projected type', () => {
            const projected = candidate({
                path: 'product.releaseDate',
                valueType: 'string',
                projection: 'product.release_date.formatted',
            });

            expect(getInlineMappingCandidates([projected])).toEqual([projected]);
        });

        it('offers a collection-scoped candidate whose value is a string', () => {
            const candidates = [candidate({ path: 'product.tagNames', valueType: 'string', contextType: 'collection' })];

            expect(getInlineMappingCandidates(candidates).map((entry) => entry.path)).toEqual(['product.tagNames']);
        });
    });

    describe('findPropertyMapping', () => {
        it('finds the root-scoped consumer that feeds the property', () => {
            const element = {
                id: 'element-id',
                component: 'Sw:Content:Text',
                acceptsContext: {
                    text: {
                        type: 'single' as const,
                        required: false,
                        scope: 'root' as const,
                        sourcePath: 'category.name',
                    },
                },
            } satisfies ContentElementNode;

            expect(findPropertyMapping(element, 'text')?.path).toBe('category.name');
        });

        it('ignores a consumer that feeds a different property', () => {
            const element = {
                id: 'element-id',
                component: 'Sw:Content:Text',
                acceptsContext: {
                    headline: {
                        type: 'single' as const,
                        required: false,
                        scope: 'root' as const,
                        sourcePath: 'category.name',
                    },
                },
            } satisfies ContentElementNode;

            expect(findPropertyMapping(element, 'text')).toBeNull();
        });

        it('ignores the undotted consumer the mutation layer mirrors for resolved wiring', () => {
            const element = {
                id: 'element-id',
                component: 'Sw:Product:Listing',
                acceptsContext: {
                    productListing: {
                        type: 'single' as const,
                        required: true,
                        propertyAlias: 'listing',
                        scope: 'root' as const,
                    },
                },
            } satisfies ContentElementNode;

            expect(findPropertyMapping(element, 'listing')).toBeNull();
        });

        it('ignores a parent-scoped consumer, which wires an ancestor rather than entity data', () => {
            const element = {
                id: 'element-id',
                component: 'Sw:Content:Text',
                acceptsContext: {
                    product: {
                        type: 'single' as const,
                        required: true,
                        propertyAlias: 'text',
                        scope: 'parent' as const,
                    },
                },
            } satisfies ContentElementNode;

            expect(findPropertyMapping(element, 'text')).toBeNull();
        });

        it('returns null for an element without any consumers', () => {
            expect(findPropertyMapping({ id: 'element-id', component: 'Sw:Content:Text' }, 'text')).toBeNull();
            expect(findPropertyMapping(null, 'text')).toBeNull();
        });
    });

    describe('getCandidatesForProperty', () => {
        it('keeps a primitive candidate only when the declared type matches exactly', () => {
            const candidates = [
                candidate({ path: 'category.name', valueType: 'string' }),
                candidate({ path: 'category.level', valueType: 'integer' }),
            ];

            expect(getCandidatesForProperty(candidates, property({ type: 'string' })).map((entry) => entry.path)).toEqual(
                ['category.name'],
            );
        });

        it('keeps a candidate matching any member of a union type', () => {
            const candidates = [
                candidate({ path: 'category.name', valueType: 'string' }),
                candidate({ path: 'category.level', valueType: 'integer' }),
                candidate({ path: 'category.active', valueType: 'boolean' }),
            ];

            const matching = getCandidatesForProperty(
                candidates,
                property({
                    type: [
                        'string',
                        'integer',
                    ],
                }),
            );

            expect(matching.map((entry) => entry.path)).toEqual([
                'category.name',
                'category.level',
            ]);
        });

        it('rejects a primitive candidate for a property declaring a class type', () => {
            const candidates = [
                candidate({ path: 'category.name', valueType: 'string' }),
                candidate({ path: 'category.media', valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity' }),
            ];

            const matching = getCandidatesForProperty(
                candidates,
                property({ type: 'Shopware\\Core\\Content\\Media\\MediaEntity' }),
            );

            expect(matching.map((entry) => entry.path)).toEqual(['category.media']);
        });

        it('rejects a primitive candidate for an unconstrained object property', () => {
            const candidates = [
                candidate({ path: 'category.name', valueType: 'string' }),
                candidate({ path: 'category.media', valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity' }),
            ];

            expect(
                getCandidatesForProperty(
                    candidates,
                    property({
                        type: 'object',
                        contextTypes: [
                            'single',
                            'collection',
                        ],
                    }),
                ).map((entry) => entry.path),
            ).toEqual(['category.media']);
        });

        // The gallery's mediaItems against a category image. Both are non-primitive class types, so the
        // class check alone admits it, and picking it produced an element that silently rendered nothing.
        it('rejects a single-media candidate for a collection property', () => {
            const candidates = [
                candidate({
                    path: 'category.media',
                    valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity',
                    contextType: 'single',
                }),
                candidate({
                    path: 'product.media',
                    valueType: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                    contextType: 'collection',
                }),
            ];

            const matching = getCandidatesForProperty(
                candidates,
                property({
                    type: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                    contextTypes: ['collection'],
                }),
            );

            expect(matching.map((entry) => entry.path)).toEqual(['product.media']);
        });

        it('rejects a collection candidate for a single-reference property', () => {
            const candidates = [
                candidate({
                    path: 'product.cover',
                    valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity',
                    contextType: 'single',
                }),
                candidate({
                    path: 'product.media',
                    valueType: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                    contextType: 'collection',
                }),
            ];

            const matching = getCandidatesForProperty(
                candidates,
                property({
                    type: 'Shopware\\Core\\Content\\Media\\MediaEntity',
                    contextTypes: ['single'],
                }),
            );

            expect(matching.map((entry) => entry.path)).toEqual(['product.cover']);
        });
    });

    describe('groupCandidates', () => {
        it('groups by catalogue group while preserving catalogue order', () => {
            const candidates = [
                candidate({ path: 'category.name', group: 'basic' }),
                candidate({ path: 'category.metaTitle', group: 'seo' }),
                candidate({ path: 'category.description', group: 'basic' }),
            ];

            expect(
                groupCandidates(candidates).map((group) => ({
                    group: group.group,
                    paths: group.candidates.map((entry) => entry.path),
                })),
            ).toEqual([
                {
                    group: 'basic',
                    paths: [
                        'category.name',
                        'category.description',
                    ],
                },
                {
                    group: 'seo',
                    paths: ['category.metaTitle'],
                },
            ]);
        });
    });
});
