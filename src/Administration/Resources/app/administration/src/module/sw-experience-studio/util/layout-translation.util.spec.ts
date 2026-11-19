import type { ContentElementNode } from 'src/core/service/content-element.types';
import { getLayoutTranslationFields } from './layout-translation.util';
import {
    translationProperty,
    translationType,
} from '../component/sw-experience-studio-translation-settings/index.spec/fixtures';

describe('layout translation fields', () => {
    const type = translationType({
        title: translationProperty(),
        shared: translationProperty({ translatable: false }),
        count: translationProperty({ type: 'integer' }),
    });

    it('flattens translatable fields in layout order, traversing slots below unknown element types', () => {
        const layout: ContentElementNode[] = [
            {
                id: 'unknown',
                component: 'Plugin:Container',
                slots: {
                    content: [{ id: 'nested', component: type.name }],
                },
            },
            { id: 'last', component: type.name },
        ];
        const original = Shopware.Utils.object.cloneDeep(layout);
        const fields = getLayoutTranslationFields(layout, (name) => (name === type.name ? type : null));

        expect(fields.map((entry) => [entry.element.id, entry.field.key, entry.elementNumber])).toEqual([
            ['nested', 'title', 2],
            ['nested', 'count', 2],
            ['last', 'title', 3],
            ['last', 'count', 3],
        ]);
        expect(layout).toEqual(original);
        expect(fields[0].element).toBe(layout[0].slots?.content[0]);
    });

    it('includes conditional fields and marks externally mapped content without hiding its entry', () => {
        const element: ContentElementNode = {
            id: 'mapped',
            component: type.name,
            acceptsContext: {
                title: { type: 'single', required: false, source: { type: 'root', id: 'product', path: 'name' } },
            },
        };
        const conditionalType = translationType({
            title: translationProperty({ adminUI: { visibleWhen: { field: 'shared', equals: true } } }),
        });
        const fields = getLayoutTranslationFields([element], () => conditionalType);

        expect(fields).toHaveLength(1);
        expect(fields[0].mappingPath).toBe('product.name');
    });

    it('returns an empty list for layouts without translatable fields', () => {
        expect(
            getLayoutTranslationFields([{ id: 'element', component: type.name }], () =>
                translationType({
                    shared: translationProperty({ translatable: false }),
                }),
            ),
        ).toEqual([]);
    });
});
