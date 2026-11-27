/**
 * @sw-package framework
 *
 * The units of a model as the break check compares them, whatever their kind: who they are, whether they are
 * public, and their facts in each flag state. `UNIT_KINDS` holds what the break check knows per kind of unit.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagState, FlagStates } from '../model/flag-states';
import type { ComponentUnit, Model } from '../model/model';
import type { SourceLine } from '../model/source-line';
import { withInheritedMembers } from './facts/member-facts';
import { factsOf } from './facts/unit-facts';
import type { UnitFacts } from './facts/unit-facts';
import { classUse, componentUse, serviceUse } from './plugin-use';
import type { PluginUse } from './plugin-use';

/** @private */
export type UnitKind = 'component' | 'service' | 'class';

/**
 * What a kind says about one of its units. `parent` is the id of the unit it inherits its elements from, which
 * reports a change they share. `markers` and `at` are those of the unit as a whole, which only a unit with a
 * declaration of its own has.
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
 * One unit of a commit. `id` starts every element id of the unit, `component:sw-card` or `service:acl`.
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
        visibility: unit.visibility,
        states: unit.states,
        markers: [],
        facts: factsOf(withInheritedMembers(unit, byName)),
    }));
}

function services(model: Model): UnitOfKind[] {
    return model.services.map((unit) => ({
        name: unit.name,
        parent: unit.class === null ? null : unitId('class', unit.class),
        visibility: unit.visibility,
        states: unit.states,
        markers: unit.markers,
        at: unit.at,
        facts: factsOf(unit),
    }));
}

function serviceClasses(model: Model): UnitOfKind[] {
    return model.serviceClasses.map((unit) => ({
        name: unit.name,
        parent: unit.extends === null ? null : unitId('class', unit.extends),
        visibility: unit.visibility,
        states: unit.states,
        markers: [],
        facts: factsOf(unit),
    }));
}

/**
 * @private
 *
 * The kinds of unit the break check compares. A new kind is one entry here.
 */
export const UNIT_KINDS: Record<UnitKind, UnitKindDefinition> = {
    component: { units: components, use: componentUse, role: { is: 'api', reportedAsWhole: false } },
    service: { units: services, use: serviceUse, role: { is: 'api', reportedAsWhole: true } },
    class: { units: serviceClasses, use: classUse, role: { is: 'grouping' } },
};

/**
 * @private
 *
 * Every unit of the model by its id.
 */
export function checkedUnits(model: Model): Map<string, CheckedUnit> {
    const units = (Object.keys(UNIT_KINDS) as UnitKind[]).flatMap((kind) =>
        UNIT_KINDS[kind].units(model).map((unit): CheckedUnit => ({ ...unit, kind, id: unitId(kind, unit.name) })),
    );

    return new Map(units.map((unit) => [unit.id, unit]));
}
