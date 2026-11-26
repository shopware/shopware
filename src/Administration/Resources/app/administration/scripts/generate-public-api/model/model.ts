/**
 * @sw-package framework
 *
 * The public API of a source tree as data. `read/build-model.ts` builds it, the printers turn it into the
 * snapshot files, and the break check compares two of them.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { Crossing, TemplateBlock } from './blocks';
import type { FlagContext, FlagStates } from './flag-states';
import type { MemberKind, UnitMembers } from './members';
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
 * What every unit has that a script declares and core registers under a name.
 *
 * - `source`: the file that declares the object behind the name, which is the file of the registration when
 *   that could not be followed. `at` is the line of that declaration, where a finding about the unit points to.
 * - `reportedIn`: the source file whose report holds the unit. It is `source`, so units of one kind that share
 *   a source share a report, or `registeredIn` for a unit of the `Shopware` object.
 * - `file`: the path of the snapshot files of the unit below `public-api/`, without extension, which is that
 *   of `reportedIn`.
 * - `visibilitySource`: where a private unit gets that from: `export` is the comment of its own declaration,
 *   and `parent` the object above it.
 * - `markers`: the `@deprecated` and `@bc-change` markers in the comment about the unit as a whole.
 */
export type RegisteredUnit = UnitMembers & {
    name: string;
    language: 'js' | 'ts';
    visibility: 'public' | 'private';
    visibilitySource: 'default' | 'export' | 'parent';
    markers: Marker[];
    registeredIn: string;
    source: string;
    at: SourceLine;
    reportedIn: string;
    file: string;
    states: FlagStates;
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
 * One registered service, the object that `Shopware.Service('<name>')` returns.
 *
 * - `registration`: `api-service` for a class below `src/core/service/api/`, which registers under the name its
 *   constructor sets, `provider` for a call that names the service and the function that provides it.
 * - `declared`: what `ServiceContainer` says about the name. `any` is an entry without a real type.
 * - `class`: the nearest class in its chain that a finding can name, see `ServiceClass`. `null` for an object
 *   literal.
 */
export type ServiceUnit = RegisteredUnit & {
    registration: 'api-service' | 'provider';
    declared: 'typed' | 'any' | 'untyped';
    class: string | null;
};

/**
 * @private
 *
 * One registered store, the object that `Shopware.Store.get('<name>')` returns.
 *
 * - `style`: `options` for a store that is given by its state, getters and actions, `setup` for one that a
 *   function builds, and `unknown` for a definition the reader cannot follow to either.
 * - `declared`: whether `PiniaRootState` has an entry for the name.
 */
export type StoreUnit = RegisteredUnit & {
    style: 'options' | 'setup' | 'unknown';
    declared: 'typed' | 'untyped';
};

/**
 * @private
 *
 * One object of the global `Shopware` object, named as plugin code writes it: `Shopware`, `Shopware.Component`
 * or `Shopware.Utils.string`. Its members are what the object holds, and an object below it is a unit of its
 * own. `registeredIn` is the file that builds the `Shopware` object, and all of these units share its report.
 *
 * - `shape`: `instance` for an instance of a class, `object` for an object literal, and `class` for a class
 *   that plugin code uses through its static members.
 * - `heldBy`: the unit above it and the member of that unit that holds it, `null` for `Shopware` itself.
 */
export type GlobalUnit = RegisteredUnit & {
    shape: 'instance' | 'object' | 'class';
    heldBy: { unit: string; kind: MemberKind; name: string } | null;
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
    stores: StoreUnit[];
    globals: GlobalUnit[];
    droppedBlocks: DroppedBlock[];
    unusedBlocks: UnusedBlock[];
}
