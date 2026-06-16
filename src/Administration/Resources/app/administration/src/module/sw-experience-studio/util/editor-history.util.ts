import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import type { EditorHistoryEntry } from '../types/editor-history.types';
import type { ContentElementNode } from '../types/content-element.types';
import { sanitizeContentElementLayoutForWrite } from './content-element.util';

const { cloneDeep } = Shopware.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export function createEditorHistoryEntry(
    layout: ContentElementNode[],
    selectedElementId: string | null,
): EditorHistoryEntry {
    return {
        layout: sanitizeContentElementLayoutForWrite(cloneDeep(layout)),
        selectedElementId,
    };
}

/**
 * @private
 * @sw-package discovery
 */
export function trimHistoryStack<T>(stack: T[], maxSize: number = EXPERIENCE_STUDIO_MAX_HISTORY_SIZE): void {
    while (stack.length > maxSize) {
        stack.shift();
    }
}
