import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { findPropertyMapping, getMappingSourceDisplayName } from '../element-mapping.util';

// Keep these examples aligned with MappingSourceReferenceTest::displayNameExamples() in PHP.
const examples: { name: string; source: ContentSystemMappingCandidate['source']; expected: string }[] = [
    { name: 'whole root source', source: { type: 'root', id: 'product' }, expected: 'product' },
    { name: 'root member', source: { type: 'root', id: 'product', path: 'name' }, expected: 'product.name' },
    {
        name: 'nested root member',
        source: { type: 'root', id: 'product', path: 'manufacturer.name' },
        expected: 'product.manufacturer.name',
    },
    {
        name: 'whole plugin source',
        source: { type: 'AcmePlugin', id: 'recommendations' },
        expected: 'AcmePlugin:recommendations',
    },
    {
        name: 'plugin member',
        source: { type: 'AcmePlugin', id: 'recommendations', path: 'title' },
        expected: 'AcmePlugin:recommendations.title',
    },
    {
        name: 'nested plugin member',
        source: { type: 'AcmePlugin', id: 'recommendations', path: 'metadata.title' },
        expected: 'AcmePlugin:recommendations.metadata.title',
    },
    {
        name: 'configuration does not change the name',
        source: { type: 'AcmePlugin', id: 'recommendations', config: { region: 'eu' }, path: 'title' },
        expected: 'AcmePlugin:recommendations.title',
    },
];

describe('mapping source display-name contract', () => {
    it.each(examples)('formats $name consistently for stored mapping lookup', ({ source, expected }) => {
        const element: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Content:Text',
            acceptsContext: {
                text: { type: 'single', required: false, scope: 'root', source },
            },
        };

        expect(getMappingSourceDisplayName(source)).toBe(expected);
        expect(findPropertyMapping(element, 'text')).toEqual({ path: expected, consumer: element.acceptsContext!.text });
    });
});
