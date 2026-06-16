import type { ContentElementNode } from './content-element.types';

/**
 * @private
 * @sw-package discovery
 */
export interface EditorHistoryEntry {
    layout: ContentElementNode[];
    selectedElementId: string | null;
}
