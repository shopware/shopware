import { Node, mergeAttributes } from '@tiptap/core';

/**
 * The single import path for tiptap in the Administration.
 *
 * `@tiptap/core` is not declared in `package.json`; it resolves only because the Meteor component library depends on
 * it and npm hoists it. That would normally be a hazard, because `MtTextEditor.js` **bundles** tiptap and ProseMirror
 * rather than importing them as bare specifiers — so anything imported here is a *different* copy from the one running
 * inside the editor, and mixing two ProseMirror instances in one editor fails as unexplained schema errors.
 *
 * Only two symbols are safe to cross that boundary, and both are re-exported below:
 *
 * - `Node.create()` builds a plain config holder. It sets `type = 'node'` as a string, and the extension manager
 *   inside Meteor's bundle discriminates extensions by `extension.type === 'node'`, never `instanceof`. So a node
 *   built from this copy is consumed correctly by that copy.
 * - `mergeAttributes()` is a pure object merge.
 *
 * Neither touches ProseMirror. **Do not widen this file** to re-export anything that does — in particular
 * `VueNodeViewRenderer`, `Editor`, or any `@tiptap/pm/*` symbol, all of which would construct objects from this copy
 * and hand them to the other one.
 *
 * The test is what a symbol *constructs*, not what it touches. `addNodeView` is fine, for instance: tiptap passes the
 * editor's own ProseMirror objects into the factory and a plain-DOM node view returns nothing but an `HTMLElement`,
 * so no object crosses in the wrong direction. `VueNodeViewRenderer` is not fine, because it builds the node view
 * machinery itself.
 *
 * Should tiptap ever need pinning, or should Meteor start externalizing it, this file is the only place to change.
 *
 * @private
 * @sw-package framework
 */
export { Node, mergeAttributes };
