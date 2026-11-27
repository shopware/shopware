/**
 * @sw-package framework
 *
 * The units of a model as the break check compares them, whatever their kind: who they are, whether they are
 * public, and their facts in each flag state. `UNIT_KINDS` holds what the break check knows per kind of unit.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagState, FlagStates } from '../model/flag-states';
import type { ComponentUnit, GlobalUnit, RegisteredUnit, Model } from '../model/model';
import type { SourceLine } from '../model/source-line';
import { withInheritedMembers } from './facts/member-facts';
import { factsOf } from './facts/unit-facts';
import type { UnitFacts } from './facts/unit-facts';
import { classUse, componentUse, globalUse, serviceUse, storeUse } from './plugin-use';
import type { PluginUse } from './plugin-use';

/** @private */
export type UnitKind = 'component' | 'service' | 'store' | 'global' | 'class';

/**
 * What a kind says about one of its units. `parent` is the id of the unit it inherits its elements from, which
 * reports a change they share. `heldBy` is the unit and its member that the unit comes and goes with, as
 * `Shopware.Utils` holds `Shopware.Utils.string`. `markers` and `at` are those of the unit as a whole, which
 * only a unit with a declaration of its own has.
 */
interface UnitOfKind {
    name: string;
    parent: string | null;
    heldBy: GlobalUnit['heldBy'];
    visibility: 'public' | 'private';
    states: FlagStates;
    markers: Marker[];
    at?: SourceLine;
    facts: Record<FlagState, UnitFacts>;
}

/**
 * @private
 *
 * One unit of a commit. `id` starts every element id of the unit, such as `component:sw-card` or `service:acl`.
 */
export interface CheckedUnit extends UnitOfKind {
    kind: UnitKind;
    id: string;
}

/**
 * What a unit of the kind is to plugin code. Plugins use an `api` unit, and `reportedAsWhole` says whether one that
 * only one commit has, or that became private, is a finding. Only the components that both commits register are
 * compared so far. A `grouping` unit is no API of its own and only reports a change that at least two units below
 * it share.
 */
type UnitRole = { is: 'api'; reportedAsWhole: boolean } | { is: 'grouping' };

interface UnitKindDefinition {
    units: (model: Model) => UnitOfKind[];
    /** How plugin code uses a unit of the kind, for the texts of a finding. */
    use: (name: string) => PluginUse;
    role: UnitRole;
}

function unitId(kind: UnitKind, name: string): string {
    return `${kind}:${name}`;
}

/** An extend child is compared with the members it inherits, so an override it drops is no change to it. */
function components(model: Model): UnitOfKind[] {
    const byName = new Map(model.components.map((unit): [string, ComponentUnit] => [unit.name, unit]));

    return model.components.map((unit) => ({
        name: unit.name,
        parent: unit.extends === null ? null : unitId('component', unit.extends),
        heldBy: null,
        visibility: unit.visibility,
        states: unit.states,
        markers: [],
        facts: factsOf(withInheritedMembers(unit, byName)),
    }));
}

/** A unit that a script declares brings its own comment and line. `parent` is the id of its parent. */
function scriptUnit(unit: RegisteredUnit, parent: string | null = null): UnitOfKind {
    return {
        name: unit.name,
        parent,
        heldBy: null,
        visibility: unit.visibility,
        states: unit.states,
        markers: unit.markers,
        at: unit.at,
        facts: factsOf(unit),
    };
}

function serviceClasses(model: Model): UnitOfKind[] {
    return model.serviceClasses.map((unit) => ({
        name: unit.name,
        parent: unit.extends === null ? null : unitId('class', unit.extends),
        heldBy: null,
        visibility: unit.visibility,
        states: unit.states,
        markers: [],
        facts: factsOf(unit),
    }));
}

/**
 * @private
 *
 * The kinds of unit the break check compares.
 */
export const UNIT_KINDS: Record<UnitKind, UnitKindDefinition> = {
    component: { units: components, use: componentUse, role: { is: 'api', reportedAsWhole: false } },
    service: {
        units: ({ services }) =>
            services.map((unit) => scriptUnit(unit, unit.class === null ? null : unitId('class', unit.class))),
        use: serviceUse,
        role: { is: 'api', reportedAsWhole: true },
    },
    store: {
        units: ({ stores }) => stores.map((unit) => scriptUnit(unit)),
        use: storeUse,
        role: { is: 'api', reportedAsWhole: true },
    },
    global: {
        units: ({ globals }) => globals.map((unit) => ({ ...scriptUnit(unit), heldBy: unit.heldBy })),
        use: globalUse,
        role: { is: 'api', reportedAsWhole: true },
    },
    class: { units: serviceClasses, use: classUse, role: { is: 'grouping' } },
};

/**
 * @private
 *
 * Every unit of the model by its id.
 */
export function checkedUnits(model: Model): Map<string, CheckedUnit> {
    const units = (Object.keys(UNIT_KINDS) as UnitKind[]).flatMap((kind) =>
        UNIT_KINDS[kind].units(model).map(
            (unit): CheckedUnit => ({
                ...unit,
                kind,
                id: unitId(kind, unit.name),
                heldBy: unit.heldBy && { ...unit.heldBy, unit: unitId(kind, unit.heldBy.unit) },
            }),
        ),
    );

    return new Map(units.map((unit) => [unit.id, unit]));
}
