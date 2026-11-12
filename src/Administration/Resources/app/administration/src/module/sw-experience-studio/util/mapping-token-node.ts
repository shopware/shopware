import { watchEffect } from 'vue';
import { Node, mergeAttributes } from 'src/app/component/meteor-wrapper/mt-text-editor/tiptap';
import { MAPPING_TOKEN_ATTRIBUTE, MAPPING_TOKEN_NODE_NAME } from './inline-mapping.util';

/**
 * @private
 */
export const MAPPING_TOKEN_CLASS = 'sw-experience-studio-mapping-token';

/**
 * Builds the `mappingToken` tiptap node: one mapped value, shown in the editor as a chip.
 *
 * `atom: true` is what makes it a chip rather than editable text. The node has no editable content, so the caret
 * steps over it and backspace deletes it whole — the author cannot land inside `product.name` and turn it into
 * `product.nme`. Legacy's `sw-text-editor` needed caret-position bracket matching (`isInsideInlineMapping` and
 * friends) precisely because it had no node type and had to infer the token boundary; a real node makes that code
 * unnecessary rather than something to port.
 *
 * The chip is drawn by a plain-DOM node view (`addNodeView`) and styled by CSS. `renderHTML` only serializes the
 * marker attribute. See `mt-text-editor/tiptap.ts`: Meteor bundles its own copy of ProseMirror, and a Vue node view
 * would construct ProseMirror objects from our copy and mix the two.
 *
 * @private
 * @sw-package discovery
 */
export function createMappingTokenNode(options: { resolveLabel: (path: string) => string }) {
    return Node.create({
        name: MAPPING_TOKEN_NODE_NAME,
        group: 'inline',
        inline: true,
        atom: true,

        addOptions() {
            return {
                resolveLabel: (path: string): string => path,
            };
        },

        addAttributes() {
            return {
                path: {
                    default: null,
                    parseHTML: (element: HTMLElement): string | null => element.getAttribute(MAPPING_TOKEN_ATTRIBUTE),
                    renderHTML: (attributes: { path?: string | null }) =>
                        attributes.path ? { [MAPPING_TOKEN_ATTRIBUTE]: attributes.path } : {},
                },
            };
        },

        parseHTML() {
            return [{ tag: `span[${MAPPING_TOKEN_ATTRIBUTE}]` }];
        },

        /**
         * Serialization only, and it must round-trip byte for byte: `mt-text-editor` parses its `modelValue` on
         * mount, serializes the result, and locks the editor behind a "review these changes" gate if the two differ.
         * That gate exists to catch tiptap silently dropping markup it does not understand, so it cannot simply be
         * suppressed. The output here therefore has to match what `tokensToEditorHtml()` writes exactly — no class,
         * no label text, nothing but the marker attribute. Anything cosmetic added here locks the editor on every
         * single mount of a text that contains a chip.
         */
        renderHTML({ HTMLAttributes }: { HTMLAttributes: object }) {
            return [
                'span',
                mergeAttributes(HTMLAttributes),
            ];
        },

        /**
         * What the author actually sees. Display is separated from serialization because the chip needs a human
         * label and a class, and neither may appear in the serialized form — see `renderHTML` above.
         *
         * This is a plain-DOM node view, which is safe across the bundle boundary described in
         * `mt-text-editor/tiptap.ts`: it *receives* the editor's own ProseMirror objects and returns nothing but an
         * `HTMLElement`, so no ProseMirror object is ever constructed from our copy of tiptap. A Vue node view via
         * `VueNodeViewRenderer` would be a different matter and stays out of bounds.
         *
         * Reactively resolve the label without changing the document or serialized HTML. Missing catalogue entries
         * fall back to their path. Destroying the node view stops its watcher, including after deleting a chip.
         */
        addNodeView() {
            const { resolveLabel } = (this as unknown as { options: { resolveLabel: (path: string) => string } }).options;

            return ({ node }: { node: { attrs: Record<string, unknown> } }) => {
                const path = typeof node.attrs.path === 'string' ? node.attrs.path : '';
                const dom = document.createElement('span');

                dom.className = MAPPING_TOKEN_CLASS;
                dom.setAttribute(MAPPING_TOKEN_ATTRIBUTE, path);
                const stopLabelWatch = watchEffect(() => {
                    dom.textContent = resolveLabel(path);
                });

                return { dom, destroy: stopLabelWatch };
            };
        },
    }).configure(options);
}
