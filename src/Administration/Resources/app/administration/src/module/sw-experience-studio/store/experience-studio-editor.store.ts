import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import type { EditorHistoryEntry } from '../types/editor-history.types';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { createEditorHistoryEntry, trimHistoryStack } from '../util/editor-history.util';

type ExperienceStudioEditorState = {
    activeLayoutId: string | null;
    historyByLayoutId: Record<
        string,
        {
            past: EditorHistoryEntry[];
            future: EditorHistoryEntry[];
        }
    >;
};

/**
 * @private
 * @sw-package discovery
 */
const experienceStudioEditorStore = Shopware.Store.register({
    id: 'experienceStudioEditor',

    state: (): ExperienceStudioEditorState => ({
        activeLayoutId: null,
        historyByLayoutId: {},
    }),

    getters: {
        canUndo: (state): boolean => {
            const activeHistory = state.activeLayoutId ? state.historyByLayoutId[state.activeLayoutId] : null;

            return Boolean(activeHistory?.past.length);
        },

        canRedo: (state): boolean => {
            const activeHistory = state.activeLayoutId ? state.historyByLayoutId[state.activeLayoutId] : null;

            return Boolean(activeHistory?.future.length);
        },
    },

    actions: {
        initialize(layoutId: string): void {
            if (!this.historyByLayoutId[layoutId]) {
                this.historyByLayoutId[layoutId] = {
                    past: [],
                    future: [],
                };
            }

            this.activeLayoutId = layoutId;
        },

        removeLayout(layoutId: string): void {
            delete this.historyByLayoutId[layoutId];

            if (this.activeLayoutId === layoutId) {
                this.activeLayoutId = null;
            }
        },

        reset(): void {
            this.activeLayoutId = null;
            this.historyByLayoutId = {};
        },

        pushToHistory(layout: ContentElementNode[], selectedElementId: string | null): void {
            const activeHistory = this.getActiveHistory();

            if (!activeHistory) {
                return;
            }

            activeHistory.past.push(createEditorHistoryEntry(layout, selectedElementId));
            trimHistoryStack(activeHistory.past, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);
            activeHistory.future = [];
        },

        undo(currentLayout: ContentElementNode[], currentSelectedElementId: string | null): EditorHistoryEntry | null {
            const activeHistory = this.getActiveHistory();
            const previousEntry = activeHistory?.past.pop();

            if (!previousEntry || !activeHistory) {
                return null;
            }

            activeHistory.future.push(createEditorHistoryEntry(currentLayout, currentSelectedElementId));
            trimHistoryStack(activeHistory.future, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);

            return previousEntry;
        },

        redo(currentLayout: ContentElementNode[], currentSelectedElementId: string | null): EditorHistoryEntry | null {
            const activeHistory = this.getActiveHistory();
            const nextEntry = activeHistory?.future.pop();

            if (!nextEntry || !activeHistory) {
                return null;
            }

            activeHistory.past.push(createEditorHistoryEntry(currentLayout, currentSelectedElementId));
            trimHistoryStack(activeHistory.past, EXPERIENCE_STUDIO_MAX_HISTORY_SIZE);

            return nextEntry;
        },

        getActiveHistory(): ExperienceStudioEditorState['historyByLayoutId'][string] | null {
            if (!this.activeLayoutId) {
                return null;
            }

            return this.historyByLayoutId[this.activeLayoutId] ?? null;
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
