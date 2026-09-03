import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import type { EditorHistoryEntry } from '../types/editor-history.types';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { createEditorHistoryEntry, trimHistoryStack } from '../util/editor-history.util';

type ExperienceStudioEditorState = {
    layoutId: string | null;
    past: EditorHistoryEntry[];
    future: EditorHistoryEntry[];
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioEditorStore = Shopware.Store.register({
    id: 'experienceStudioEditor',

    state: (): ExperienceStudioEditorState => ({
        layoutId: null,
        past: [],
        future: [],
    }),

    getters: {
        canUndo: (state): boolean => state.past.length > 0,

        canRedo: (state): boolean => state.future.length > 0,
    },

    actions: {
        initialize(layoutId: string): void {
            if (this.layoutId === layoutId) {
                return;
            }

            this.reset();
            this.layoutId = layoutId;
        },

        reset(): void {
            this.layoutId = null;
            this.past = [];
            this.future = [];
        },

        pushToHistory(layout: ContentElementNode[], selectedElementId: string | null): void {
            this.past.push(createEditorHistoryEntry(layout, selectedElementId));
            trimHistoryStack(this.past, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);
            this.future = [];
        },

        undo(currentLayout: ContentElementNode[], currentSelectedElementId: string | null): EditorHistoryEntry | null {
            const previousEntry = this.past.pop();

            if (!previousEntry) {
                return null;
            }

            this.future.push(createEditorHistoryEntry(currentLayout, currentSelectedElementId));
            trimHistoryStack(this.future, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);

            return previousEntry;
        },

        redo(currentLayout: ContentElementNode[], currentSelectedElementId: string | null): EditorHistoryEntry | null {
            const nextEntry = this.future.pop();

            if (!nextEntry) {
                return null;
            }

            this.past.push(createEditorHistoryEntry(currentLayout, currentSelectedElementId));
            trimHistoryStack(this.past, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);

            return nextEntry;
        },
    },
});

/**
 * @private
 */
export type ExperienceStudioEditorStore = ReturnType<typeof experienceStudioEditorStore>;

/**
 * @private
 */
export default experienceStudioEditorStore;
