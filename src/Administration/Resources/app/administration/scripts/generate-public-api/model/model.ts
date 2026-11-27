/**
 * @sw-package framework
 *
 * The public API of a source tree as data. `read/build-model.ts` builds it, the printers turn it into the
 * snapshot files, and the break check compares two of them.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Crossing, TemplateBlock } from './blocks';
import type { FlagContext, FlagStates } from './flag-states';
import type { UnitMembers } from './members';
import type { SourceLine } from './source-line';

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
    UnitMembers & {
        file: string;
        blocks: TemplateBlock[];
        crossings: Crossing[];
    };

/**
 * @private
 *
 * One registered service, the object that `Shopware.Service('<name>')` returns.
 *
 * - `registration`: `api-service` for a class below `src/core/service/api/`, which registers under the name its
 *   constructor sets, `provider` for a call that names the service and the function that provides it.
 * - `declared`: what `ServiceContainer` says about the name. `any` is an entry without a real type.
 * - `source`: the file that declares the object, which is the file of the registration when that could not be
 *   followed. `at` is the line of that declaration, where a finding about the service points to.
 * - `file`: the path of the snapshot files of `source` below `public-api/`, without extension. Services that
 *   share a source share a report.
 * - `markers`: the `@deprecated` and `@bc-change` markers above the declaration.
 * - `class`: the nearest class in its chain that a finding can name, see `ServiceClass`. `null` for an object
 *   literal.
 */
export type ServiceUnit = UnitMembers & {
    name: string;
    registration: 'api-service' | 'provider';
    declared: 'typed' | 'any' | 'untyped';
    language: 'js' | 'ts';
    visibility: 'public' | 'private';
    visibilitySource: 'default' | 'export';
    markers: Marker[];
    registeredIn: string;
    source: string;
    at: SourceLine;
    file: string;
    states: FlagStates;
    class: string | null;
};

/**
 * @private
 *
 * A class that services are instances of, with the members an instance of it has. It is public when one of its
 * services is, and exists in their flag states. `extends` names the nearest class it extends that is a
 * `ServiceClass` too. An anonymous class, and two classes of one name, are none, because a finding names a class
 * by its name. The snapshot does not show the classes. The break check reports a change once on the class when
 * several services share it.
 */
export type ServiceClass = UnitMembers & {
    name: string;
    extends: string | null;
    visibility: 'public' | 'private';
    source: string;
    at: SourceLine;
    states: FlagStates;
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
    services: ServiceUnit[];
    serviceClasses: ServiceClass[];
    droppedBlocks: DroppedBlock[];
    unusedBlocks: UnusedBlock[];
}
