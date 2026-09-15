/**
 * @sw-package framework
 * @private
 *
 * DOM side of the block inspector: finding marked elements, drawing the highlight overlay and
 * picking a block by clicking on it. Framework-free so that it can be exercised in jsdom; the Vue
 * devtools glue in `sw-vue-devtools-block-inspector.ts` only wires these pieces to the devtools API.
 */

import { BLOCK_MARKER_ATTRIBUTE, parseBlockMarker } from 'src/core/factory/block-inspector';

const OVERLAY_CLASS = 'sw-block-inspector-overlay';
const LABEL_CLASS = 'sw-block-inspector-overlay__label';

/**
 * Names of the blocks starting at the element, innermost first.
 *
 * @private
 */
export function blockNamesOf(element: Element): string[] {
    return parseBlockMarker(element.getAttribute(BLOCK_MARKER_ATTRIBUTE));
}

/**
 * The element itself or its closest ancestor that starts a block.
 *
 * @private
 */
export function findMarkedElement(target: EventTarget | null): Element | null {
    if (!(target instanceof Element)) {
        return null;
    }

    return target.closest(`[${BLOCK_MARKER_ATTRIBUTE}]`);
}

/**
 * Every block name that encloses the element, innermost first, walking up through all marked
 * ancestors.
 *
 * @private
 */
export function enclosingBlockNames(target: Element | null): string[] {
    const names: string[] = [];
    let current = findMarkedElement(target);

    while (current) {
        blockNamesOf(current).forEach((name) => {
            if (!names.includes(name)) {
                names.push(name);
            }
        });

        current = findMarkedElement(current.parentElement);
    }

    return names;
}

/**
 * All marked elements currently in the DOM, grouped by block name in document order.
 *
 * @private
 */
export function collectMarkedBlocks(root: ParentNode = document): Map<string, Element[]> {
    const blocks = new Map<string, Element[]>();

    root.querySelectorAll(`[${BLOCK_MARKER_ATTRIBUTE}]`).forEach((element) => {
        blockNamesOf(element).forEach((name) => {
            const elements = blocks.get(name) ?? [];
            elements.push(element);
            blocks.set(name, elements);
        });
    });

    return blocks;
}

/**
 * Elements in the DOM that start the given block.
 *
 * @private
 */
export function findBlockElements(blockName: string, root: ParentNode = document): Element[] {
    return Array.from(root.querySelectorAll(`[${BLOCK_MARKER_ATTRIBUTE}~="${blockName}"]`));
}

/**
 * A block with the blocks nested inside it.
 *
 * @private
 */
export type ContainedBlock = {
    name: string;
    children: ContainedBlock[];
};

/**
 * Maps every block that starts on one of the elements to the block enclosing it.
 *
 * A block starts either on its own element or on an element it shares with outer blocks, so the
 * enclosing chain of a starting element yields the parent of every block: the entry right after the
 * block itself. Blocks are keyed by name, matching the rest of the inspector, so a name that starts
 * in several places is recorded once, at its first occurrence in document order.
 */
function collectBlockParents(elements: Element[], namesOf: (element: Element) => string[]): Map<string, string | null> {
    const parents = new Map<string, string | null>();

    elements.forEach((element) => {
        const chain = enclosingBlockNames(element);

        namesOf(element).forEach((name) => {
            if (parents.has(name)) {
                return;
            }

            const chainIndex = chain.indexOf(name);
            parents.set(name, chainIndex === -1 ? null : (chain[chainIndex + 1] ?? null));
        });
    });

    return parents;
}

/** Turns a child-to-parent map into nested nodes, starting at the blocks whose parent is `parent`. */
function nestBlocks(parents: Map<string, string | null>, parent: string | null): ContainedBlock[] {
    const nested: ContainedBlock[] = [];

    parents.forEach((value, name) => {
        if (value === parent) {
            nested.push({ name, children: nestBlocks(parents, name) });
        }
    });

    return nested;
}

/**
 * The blocks inside `blockName`, nested the way they nest in the DOM.
 *
 * @private
 */
export function containedBlockTree(element: Element, blockName: string): ContainedBlock[] {
    const startElements: Element[] = [
        element,
        ...Array.from(element.querySelectorAll(`[${BLOCK_MARKER_ATTRIBUTE}]`)),
    ];

    const parents = collectBlockParents(startElements, (startElement) => {
        const ownNames = blockNamesOf(startElement);
        // On the picked element only the names nested inside the picked block count; everything
        // after it in the marker encloses it.
        const pickedIndex = ownNames.indexOf(blockName);

        return startElement === element && pickedIndex !== -1 ? ownNames.slice(0, pickedIndex) : ownNames;
    });

    return nestBlocks(parents, blockName);
}

/**
 * Every block currently in the page, nested the way the blocks nest in the DOM.
 *
 * The outermost blocks become the roots. Use it to show the blocks the way an element inspector
 * shows the document.
 *
 * @private
 */
export function documentBlockTree(root: ParentNode = document): ContainedBlock[] {
    const elements = Array.from(root.querySelectorAll(`[${BLOCK_MARKER_ATTRIBUTE}]`));
    const parents = collectBlockParents(elements, blockNamesOf);

    // A parent outside the scanned scope cannot be rendered, so such a block becomes a root itself.
    parents.forEach((parent, name) => {
        if (parent !== null && !parents.has(parent)) {
            parents.set(name, null);
        }
    });

    return nestBlocks(parents, null);
}

type Rect = { top: number; left: number; right: number; bottom: number };

/**
 * Smallest rectangle around all elements, in viewport coordinates. Elements without layout
 * (display none, detached) are ignored. Null when nothing has a box.
 *
 * @private
 */
export function unionRect(elements: Element[]): Rect | null {
    let union: Rect | null = null;

    elements.forEach((element) => {
        const rect = element.getBoundingClientRect();

        if (rect.width === 0 && rect.height === 0) {
            return;
        }

        union = union
            ? {
                  top: Math.min(union.top, rect.top),
                  left: Math.min(union.left, rect.left),
                  right: Math.max(union.right, rect.right),
                  bottom: Math.max(union.bottom, rect.bottom),
              }
            : { top: rect.top, left: rect.left, right: rect.right, bottom: rect.bottom };
    });

    return union;
}

/** How far the label overlaps the frame border, so the two line up. */
const LABEL_BORDER_OFFSET = 2;

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Where the label of a frame goes, in viewport coordinates.
 *
 * The label follows the frame but never leaves the screen: a block that starts left of the viewport,
 * or below its bottom edge, still shows its name at the matching edge. The label sits above the
 * frame when there is room and moves onto the frame's top edge otherwise.
 *
 * @private
 */
export function labelPosition(
    frameRect: { top: number; left: number },
    labelSize: { width: number; height: number },
    viewport: { width: number; height: number },
): { left: number; top: number; inside: boolean } {
    const above = frameRect.top - labelSize.height;
    const inside = above < 0;

    return {
        left: clamp(frameRect.left - LABEL_BORDER_OFFSET, 0, Math.max(0, viewport.width - labelSize.width)),
        top: clamp(inside ? frameRect.top - LABEL_BORDER_OFFSET : above, 0, Math.max(0, viewport.height - labelSize.height)),
        inside,
    };
}

/**
 * A highlight frame with a block name label, like the element picker of the browser devtools.
 *
 * @private
 */
export type BlockOverlay = {
    /** Frames the elements and labels them with the block name. Replaces any previous highlight. */
    show(blockName: string, elements: Element[]): void;
    /** Removes the frame. */
    hide(): void;
    /** Re-measures the framed elements, for scroll and resize. */
    refresh(): void;
    /** Removes the frame and every listener. */
    destroy(): void;
};

const OVERLAY_STYLE = `
.${OVERLAY_CLASS} {
    position: fixed;
    z-index: 2147483000;
    pointer-events: none;
    box-sizing: border-box;
    border: 2px solid #de294c;
    background: rgba(222, 41, 76, 0.08);
}

/* Placed in viewport coordinates by refresh(), so it stays on screen when the block does not. */
.${LABEL_CLASS} {
    position: fixed;
    top: 0;
    left: 0;
    max-width: 100vw;
    padding: 2px 6px;
    font: 12px/1.5 Menlo, Consolas, monospace;
    color: #fff;
    background: #de294c;
    border-radius: 3px 3px 0 0;
    white-space: nowrap;
}

.${OVERLAY_CLASS}--label-inside .${LABEL_CLASS} {
    border-radius: 0 0 3px 0;
}
`;

/**
 * Creates the overlay. The frame is appended to `document.body` on first `show` and follows the
 * framed elements on scroll and resize.
 *
 * @private
 */
export function createBlockOverlay(doc: Document = document): BlockOverlay {
    const style = doc.createElement('style');
    style.textContent = OVERLAY_STYLE;
    doc.head.appendChild(style);

    const frame = doc.createElement('div');
    frame.className = OVERLAY_CLASS;
    frame.hidden = true;

    const label = doc.createElement('span');
    label.className = LABEL_CLASS;
    frame.appendChild(label);
    doc.body.appendChild(frame);

    let framedElements: Element[] = [];
    let listening = false;

    const refresh = (): void => {
        const rect = unionRect(framedElements);

        if (!rect) {
            frame.hidden = true;

            return;
        }

        frame.hidden = false;
        frame.style.top = `${rect.top}px`;
        frame.style.left = `${rect.left}px`;
        frame.style.width = `${rect.right - rect.left}px`;
        frame.style.height = `${rect.bottom - rect.top}px`;

        const labelRect = label.getBoundingClientRect();
        const view = doc.defaultView;
        const position = labelPosition(
            rect,
            { width: labelRect.width, height: labelRect.height },
            { width: view?.innerWidth ?? 0, height: view?.innerHeight ?? 0 },
        );

        label.style.left = `${position.left}px`;
        label.style.top = `${position.top}px`;
        frame.classList.toggle(`${OVERLAY_CLASS}--label-inside`, position.inside);
    };

    const startListening = (): void => {
        if (listening) {
            return;
        }

        doc.addEventListener('scroll', refresh, { capture: true, passive: true });
        doc.defaultView?.addEventListener('resize', refresh);
        listening = true;
    };

    const stopListening = (): void => {
        if (!listening) {
            return;
        }

        doc.removeEventListener('scroll', refresh, { capture: true });
        doc.defaultView?.removeEventListener('resize', refresh);
        listening = false;
    };

    return {
        show(blockName, elements) {
            framedElements = elements;
            label.textContent = `{% block ${blockName} %}`;
            startListening();
            refresh();
        },
        hide() {
            framedElements = [];
            frame.hidden = true;
            stopListening();
        },
        refresh,
        destroy() {
            stopListening();
            frame.remove();
            style.remove();
        },
    };
}

/**
 * @private
 */
export type OverlayDismissalOptions = {
    /** Keeps the overlay while this returns true, for example during picking. */
    unless?: () => boolean;
};

/**
 * Hides the overlay as soon as the developer interacts with the page: on Escape or on a pointer
 * press anywhere. Neither devtools generation tells a plugin when its panel closes, so a highlight
 * would otherwise stay until something else replaces it. Returns a function that removes the
 * listeners.
 *
 * @private
 */
export function hideOverlayOnPageInteraction(
    overlay: Pick<BlockOverlay, 'hide'>,
    options: OverlayDismissalOptions = {},
    doc: Document = document,
): () => void {
    const hide = (): void => {
        if (options.unless?.()) {
            return;
        }

        overlay.hide();
    };

    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            hide();
        }
    };

    doc.addEventListener('keydown', onKeyDown, true);
    doc.addEventListener('pointerdown', hide, true);

    return () => {
        doc.removeEventListener('keydown', onKeyDown, true);
        doc.removeEventListener('pointerdown', hide, true);
    };
}

/**
 * What the picker reports while the pointer moves and when a block is picked.
 *
 * @private
 */
export type BlockPickerHandlers = {
    /** The innermost block under the pointer changed; null when the pointer left all blocks. */
    onHover(blockName: string | null, elements: Element[]): void;
    /** The user clicked on a block. */
    onPick(blockName: string, element: Element): void;
    /** Picking ended, by a pick or by Escape. */
    onStop?(): void;
};

/**
 * Lets the user pick a block by clicking on it. The click is swallowed so the page does not act on
 * it. Escape cancels. Returns a function that stops picking early.
 *
 * @private
 */
export function startBlockPicking(handlers: BlockPickerHandlers, doc: Document = document): () => void {
    let hoveredName: string | null = null;
    let stopped = false;

    const stop = (): void => {
        if (stopped) {
            return;
        }

        stopped = true;
        doc.removeEventListener('mousemove', onMouseMove, true);
        doc.removeEventListener('click', onClick, true);
        doc.removeEventListener('keydown', onKeyDown, true);
        handlers.onStop?.();
    };

    function onMouseMove(event: MouseEvent): void {
        const marked = findMarkedElement(event.target);
        const name = marked ? (blockNamesOf(marked)[0] ?? null) : null;

        if (name === hoveredName) {
            return;
        }

        hoveredName = name;
        handlers.onHover(name, name ? findBlockElements(name, doc) : []);
    }

    function onClick(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();

        const marked = findMarkedElement(event.target);
        const name = marked ? blockNamesOf(marked)[0] : undefined;

        if (!marked || !name) {
            return;
        }

        stop();
        handlers.onPick(name, marked);
    }

    function onKeyDown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            stop();
        }
    }

    doc.addEventListener('mousemove', onMouseMove, true);
    doc.addEventListener('click', onClick, true);
    doc.addEventListener('keydown', onKeyDown, true);

    return stop;
}
