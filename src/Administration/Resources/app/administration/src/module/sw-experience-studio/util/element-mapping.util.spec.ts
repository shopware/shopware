import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import {
    findPropertyMapping,
    getCandidatesForProperty,
    groupCandidates,
    isMappableProperty,
} from './element-mapping.util';

function property(overrides: Partial<ContentSystemElementTypeProperty> = {}): ContentSystemElementTypeProperty {
    return {
        type: 'string',
        translatable: false,
        enum: null,
        default: null,
        required: false,
        mappable: false,
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

    describe('findPropertyMapping', () => {
        it('finds the root-scoped consumer that feeds the property', () => {
            const element = {
                id: 'element-id',
                component: 'Sw:Content:Text',
                acceptsContext: {
                    'category.name': {
                        type: 'single' as const,
                        required: false,
                        propertyAlias: 'text',
                        scope: 'root' as const,
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
                    'category.name': {
                        type: 'single' as const,
                        required: false,
                        propertyAlias: 'headline',
                        scope: 'root' as const,
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

            expect(getCandidatesForProperty(candidates, property({ type: 'object' })).map((entry) => entry.path)).toEqual(
                ['category.media'],
            );
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
