import type { ContentElementNode } from 'src/core/service/content-element.types';

/**
 * @private
 * @sw-package discovery
 */
export interface EditorHistoryEntry {
    layout: ContentElementNode[];
    selectedElementId: string | null;
}
