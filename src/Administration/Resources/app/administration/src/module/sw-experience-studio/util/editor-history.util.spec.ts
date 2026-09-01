import type { ContentElementNode } from 'src/core/service/content-element.types';
import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import { createEditorHistoryEntry, trimHistoryStack } from './editor-history.util';

const { cloneDeep } = Shopware.Utils.object;

describe('module/sw-experience-studio/util/editor-history.util', () => {
    const layout: ContentElementNode[] = [
        {
            id: 'root-1',
            component: 'layout:section',
            properties: {
                name: 'Section',
            },
        },
    ];

    it('carries attributed specification values into the history snapshot', () => {
        const source = cloneDeep(layout);

        source[0].attributedSpecifications = {
            'layout:section': 'SwagPromotion',
            headline: 'SwagBlog',
        };

        const entry = createEditorHistoryEntry(source, 'root-1');

        expect(entry.selectedElementId).toBe('root-1');
        expect(entry.layout[0].attributedSpecifications).toEqual({
            'layout:section': 'SwagPromotion',
            headline: 'SwagBlog',
        });
    });

    it('trims history stacks to the configured maximum size', () => {
        const stack = Array.from({ length: 12 }, (_, index) => index);

        trimHistoryStack(stack, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);

        expect(stack).toHaveLength(EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);
        expect(stack[0]).toBe(2);
        expect(stack[9]).toBe(11);
    });
});
