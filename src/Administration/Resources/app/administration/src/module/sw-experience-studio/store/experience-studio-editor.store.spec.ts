/**
 * @sw-package discovery
 */

import type { ContentElementNode } from 'src/core/service/content-element.types';
import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import './experience-studio-editor.store';

describe('src/module/sw-experience-studio/store/experience-studio-editor.store.ts', () => {
    const layout: ContentElementNode[] = [
        {
            id: 'root-1',
            component: 'layout:section',
        },
    ];

    const getStore = () => Shopware.Store.get('experienceStudioEditor');

    beforeEach(() => {
        getStore().reset();
    });

    it('should register a store', () => {
        expect(Shopware.Store.get('experienceStudioEditor')).toBeDefined();
    });

    it('should track undo and redo across up to 10 history entries', () => {
        const store = getStore();

        for (let index = 0; index < EXPERIENCE_STUDIO_MAX_HISTORY_SIZE + 2; index += 1) {
            store.pushToHistory(
                [
                    {
                        id: `root-${index}`,
                        component: 'layout:section',
                    },
                ],
                null,
            );
        }

        expect(store.past).toHaveLength(EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);
        expect(store.past[0].layout[0].id).toBe('root-2');

        const currentLayout: ContentElementNode[] = [
            {
                id: 'root-current',
                component: 'layout:section',
            },
        ];

        const undoEntry = store.undo(currentLayout, 'root-current');

        expect(undoEntry?.layout[0].id).toBe('root-11');
        expect(store.canRedo).toBe(true);
        expect(store.future[0].layout[0].id).toBe('root-current');

        const redoEntry = store.redo(
            [
                {
                    id: 'root-11',
                    component: 'layout:section',
                },
            ],
            'root-11',
        );

        expect(redoEntry?.layout[0].id).toBe('root-current');
    });

    it('should clear future history when a new mutation is recorded', () => {
        const store = getStore();

        store.pushToHistory(layout, null);
        store.undo(
            [
                {
                    id: 'root-2',
                    component: 'layout:section',
                },
            ],
            null,
        );

        expect(store.canRedo).toBe(true);

        store.pushToHistory(layout, null);

        expect(store.canRedo).toBe(false);
        expect(store.future).toHaveLength(0);
    });

    it('should reset history when initialized for a different layout', () => {
        const store = getStore();

        store.initialize('layout-a');
        store.pushToHistory(layout, null);
        store.initialize('layout-b');

        expect(store.past).toHaveLength(0);
        expect(store.future).toHaveLength(0);
        expect(store.layoutId).toBe('layout-b');
    });
});
