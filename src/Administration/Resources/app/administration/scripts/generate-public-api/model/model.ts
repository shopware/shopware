/**
 * @sw-package framework
 *
 * The public API of a source tree as data. `read/build-model.ts` builds it, the printers turn it into the
 * snapshot files, and the break check compares two of them.
 */
import type { Crossing, TemplateBlock } from './blocks';
import type { FlagContext, FlagStates } from './flag-states';
import type { ComponentMembers } from './members';

/**
 * @private
 *
 * One registration call. `source` is the file the registration imports, `sfc` the `.vue` file that it only
 * re-exports, and `template` its Twig template. `states` are the flag states in which the call runs.
 */
export interface Registration {
    kind: 'register' | 'extend';
    name: string;
    extends: string | null;
    language: 'js' | 'ts' | 'vue';
    visibility: 'public' | 'private';
    visibilitySource: 'default' | 'export' | 'registration';
    registeredIn: string;
    source: string;
    sfc: string | null;
    template: string | null;
    states: FlagStates;
}

/**
 * @private
 *
 * A top-level block of an extend or override template that the runtime drops without a warning:
 * - `missing`: the component has no block of that name.
 * - `shadowed`: its target sits below a block that the same template redeclares.
 * - `duplicate`: the template declares the block a second time, and only the first declaration applies.
 */
export interface DroppedBlock {
    kind: 'missing' | 'shadowed' | 'duplicate';
    block: string;
    file: string;
}

/**
 * @private
 *
 * One registered component. `file` is the path of its snapshot files below `public-api/`, without extension.
 * `blocks` holds the blocks of the template it renders, with those it inherits along its extend chain and the
 * core overrides applied, limited to the flag states in which the whole chain is registered. `crossings` holds
 * the elements and `v-if` chains of that template that cross a block boundary. `members` holds what the options
 * object declares and what that template offers besides blocks, and `notes` what could not be read of either.
 */
export type ComponentUnit = Registration &
    ComponentMembers & {
        file: string;
        blocks: TemplateBlock[];
        crossings: Crossing[];
    };

/**
 * @private
 *
 * A block of a template that no registered component and no core override uses.
 */
export interface UnusedBlock {
    block: string;
    file: string;
}

/**
 * @private
 *
 * `droppedBlocks` are blocks that an extend or override template declares and the runtime never renders. They
 * are in no component, although their template declares them. Neither are the `unusedBlocks`.
 */
export interface Model {
    flags: FlagContext;
    components: ComponentUnit[];
    droppedBlocks: DroppedBlock[];
    unusedBlocks: UnusedBlock[];
}
