/**
 * @sw-package framework
 *
 * The units of a model as the break check compares them, whatever their kind: who they are, whether they are
 * public, and their facts in each flag state. `UNIT_KINDS` holds what the break check knows per kind of unit.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagState, FlagStates } from '../model/flag-states';
import type { ComponentUnit, RegisteredUnit, Model } from '../model/model';
import type { SourceLine } from '../model/source-line';
import { withInheritedMembers } from './facts/member-facts';
import { factsOf } from './facts/unit-facts';
import type { UnitFacts } from './facts/unit-facts';
import { componentUse, serviceUse, storeUse } from './plugin-use';
import type { PluginUse } from './plugin-use';

/** @private */
export type UnitKind = 'component' | 'service' | 'store';

/**
 * What a kind says about one of its units. `parent` is the name of the unit it inherits its elements from,
 * which reports a change they share. `markers` and `at` are those of the unit as a whole, which only a unit
 * with a declaration of its own has.
 */
interface UnitOfKind {
    name: string;
    parent: string | null;
    visibility: 'public' | 'private';
    states: FlagStates;
    markers: Marker[];
    at?: SourceLine;
    facts: Record<FlagState, UnitFacts>;
}

/**
 * @private
 *
 * One unit of a commit. `id` starts every element id of the unit, such as `component:sw-card` or `service:acl`, and
 * `parent` is the id of its parent.
 */
export interface CheckedUnit extends UnitOfKind {
    kind: UnitKind;
    id: string;
}

interface UnitKindDefinition {
    units: (model: Model) => UnitOfKind[];
    /** How plugin code uses a unit of the kind, for the texts of a finding. */
    use: (name: string) => PluginUse;
    /**
     * Whether a unit that only one commit has, or that became private, is a finding. Only the components that
     * both commits register are compared so far.
     */
    reportedAsWhole: boolean;
}

/** An extend child is compared with the members it inherits, so an override it drops is no change to it. */
function components(model: Model): UnitOfKind[] {
    const byName = new Map(model.components.map((unit): [string, ComponentUnit] => [unit.name, unit]));

    return model.components.map((unit) => ({
        name: unit.name,
        parent: unit.extends,
        visibility: unit.visibility,
        states: unit.states,
        markers: [],
        facts: factsOf(withInheritedMembers(unit, byName)),
    }));
}

/** A unit that a script declares brings its own comment and line, and it inherits from no other unit. */
function declared(units: RegisteredUnit[]): UnitOfKind[] {
    return units.map((unit) => ({
        name: unit.name,
        parent: null,
        visibility: unit.visibility,
        states: unit.states,
        markers: unit.markers,
        at: unit.at,
        facts: factsOf(unit),
    }));
}

/**
 * @private
 *
 * The kinds of unit the break check compares. A new kind is one entry here.
 */
export const UNIT_KINDS: Record<UnitKind, UnitKindDefinition> = {
    component: { units: components, use: componentUse, reportedAsWhole: false },
    service: { units: ({ services }) => declared(services), use: serviceUse, reportedAsWhole: true },
    store: { units: ({ stores }) => declared(stores), use: storeUse, reportedAsWhole: true },
};

function unitId(kind: UnitKind, name: string): string {
    return `${kind}:${name}`;
}

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
                parent: unit.parent === null ? null : unitId(kind, unit.parent),
            }),
        ),
    );

    return new Map(units.map((unit) => [unit.id, unit]));
}
