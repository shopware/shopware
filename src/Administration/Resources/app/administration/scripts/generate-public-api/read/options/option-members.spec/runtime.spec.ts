/**
 * @sw-package framework
 *
 * The generator reads the members of a component from the syntax of its options object, without running it.
 * This spec loads every component of the Administration and compares the names with what the object holds at
 * runtime, so a construct the reader does not understand shows up here instead of as a missing member.
 */
import { byCodePoint } from '../../../helpers/code-point-order';
import type { MemberKind } from '../../../model/members';
import type { ComponentUnit } from '../../../model/model';
import { ADMINISTRATION_ROOT, currentMajorFlags } from '../../../source/checkout';
import { readSourceTree } from '../../../source/source-tree';
import { buildModel } from '../../build-model';
import { loadOptions, loadSource, OPTION_KINDS, runtimeNames } from './runtime-options';
import type { OptionKind, RuntimeNames } from './runtime-options';

// The section of the options object a note names, by the kind of its members.
const SECTIONS: Record<OptionKind, string> = {
    mixin: 'mixins',
    inject: 'inject',
    provide: 'provide',
    prop: 'props',
    emit: 'emits',
    data: 'data',
    computed: 'computed',
    method: 'methods',
};

const MIXIN_REGISTRATION = /\bMixin\s*\.\s*register\s*\(/;

function generatedNames(unit: ComponentUnit, kind: MemberKind): string[] {
    const names = unit.members.filter((member) => member.kind === kind).map(({ name }) => name);

    // The runtime side knows the top level of `data()` and counts the mixins.
    if (kind === 'data') {
        return names.filter((name) => !name.includes('.'));
    }

    return kind === 'mixin' ? names.map((_name, position) => `#${position}`) : names;
}

/** A note says that the generator knows it misses members of the section, or of the whole object. */
function isIncomplete(unit: ComponentUnit, kind: OptionKind): boolean {
    return unit.notes.some(
        (note) => note.startsWith(`unresolved ${SECTIONS[kind]} `) || note.startsWith('unresolved options '),
    );
}

function difference(unit: ComponentUnit, kind: OptionKind, runtime: string[]): string[] {
    const generated = generatedNames(unit, kind);
    const unknown = generated.filter((name) => !runtime.includes(name));
    const missed = isIncomplete(unit, kind) ? [] : runtime.filter((name) => !generated.includes(name));

    return [
        ...unknown.map((name) => `${unit.name}: ${kind} ${name} is in the snapshot, not in the options object`),
        ...missed.map((name) => `${unit.name}: ${kind} ${name} is in the options object, not in the snapshot`),
    ];
}

describe('scripts/generate-public-api/read/options/option-members against the runtime', () => {
    const tree = readSourceTree(ADMINISTRATION_ROOT);
    const model = buildModel(tree, { majorFlags: currentMajorFlags() });
    const scripted = model.components.filter(({ language }) => language !== 'vue');
    const loaded: { unit: ComponentUnit; names: RuntimeNames }[] = [];
    const notLoaded: string[] = [];

    beforeAll(() => {
        // A component looks up its mixins while its source runs, and the modules register theirs on import.
        tree.files.filter((file) => MIXIN_REGISTRATION.test(tree.read(file))).forEach(loadSource);

        scripted.forEach((unit) => {
            try {
                loaded.push({ unit, names: runtimeNames(loadOptions(unit.source)) });
            } catch (error) {
                notLoaded.push(`${unit.name}: ${String(error).split('\n')[0]}`);
            }
        });
    });

    it('loads the options object of every component', () => {
        expect(notLoaded).toEqual([]);
        expect(loaded.length).toBeGreaterThan(1000);
    });

    it.each(OPTION_KINDS)('lists the %s names the options object holds', (kind) => {
        const compared = loaded.flatMap(({ unit, names }) => (names[kind] === null ? [] : [{ unit, runtime: names[kind] }]));
        const differences = compared.flatMap(({ unit, runtime }) => difference(unit, kind, runtime)).sort(byCodePoint);

        expect(differences).toEqual([]);
        // Without names on the runtime side the comparison above would pass whatever the generator reads.
        expect(compared.filter(({ runtime }) => runtime.length > 0).length).toBeGreaterThan(20);
    });

    /*
     * `data()` and `provide()` run with a stand-in for `this`. A function that needs a real instance is not
     * compared, so the share of those has to stay small for the comparison to mean something.
     */
    it.each(['data', 'provide'] as const)('runs %s() of nearly every component', (kind) => {
        const notRun = loaded.filter(({ names }) => names[kind] === null).map(({ unit }) => unit.name);

        expect(notRun.length).toBeLessThan(loaded.length / 50);
    });
});
