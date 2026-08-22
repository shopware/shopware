import type { ContentElementNode } from 'src/core/service/api/content-element.types';

/**
 * @private
 * @sw-package discovery
 */
export interface EditorHistoryEntry {
    layout: ContentElementNode[];
    selectedElementId: string | null;
}
