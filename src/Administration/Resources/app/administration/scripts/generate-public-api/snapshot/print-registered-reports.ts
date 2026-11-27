/**
 * @sw-package framework
 *
 * Prints the `.api.md` reports of the units that a script declares and core registers under a name: one report
 * per source file and kind of unit, with one outline per unit.
 */
import type { FlagContext } from '../model/flag-states';
import { KINDS } from '../model/members';
import type { Model, RegisteredUnit, ServiceUnit, StoreUnit } from '../model/model';
import { memberSections } from './print-members';
import { flagStateMarker, line, markerLabels, notesSection, printOutline, printReportFile } from './report-outline';
import type { Line } from './report-outline';

/**
 * How the reports name a kind of unit. `facets` is what the header line says about a unit besides its name,
 * its visibility and the language of its source.
 */
interface ReportKind<Unit extends RegisteredUnit> {
    word: string;
    plural: string;
    units: (model: Model) => Unit[];
    facets: (unit: Unit) => string[];
}

const SERVICE_REPORTS: ReportKind<ServiceUnit> = {
    word: 'service',
    plural: 'services',
    units: ({ services }) => services,
    facets: ({ declared, registration }) => [declared, registration],
};

const STORE_REPORTS: ReportKind<StoreUnit> = {
    word: 'store',
    plural: 'stores',
    units: ({ stores }) => stores,
    facets: ({ declared, style }) => [declared, style],
};

function header<Unit extends RegisteredUnit>({ word, facets }: ReportKind<Unit>, unit: Unit, flags: FlagContext): Line[] {
    return [
        line(
            [
                `${word} ${unit.name}`,
                unit.visibility,
                unit.language,
                ...facets(unit),
            ].join('  '),
            flagStateMarker(unit.states, flags),
            ...markerLabels(unit.markers),
        ),
        line(`  registered ${unit.registeredIn}`),
        line(`  source ${unit.source}`),
        ...(unit.visibility === 'private' ? [line(`  visibility-source ${unit.visibilitySource}`)] : []),
    ];
}

function capitalized(word: string): string {
    return `${word.charAt(0).toUpperCase()}${word.slice(1)}`;
}

function printReport<Unit extends RegisteredUnit>(
    kind: ReportKind<Unit>,
    source: string,
    units: Unit[],
    flags: FlagContext,
): string {
    return printReportFile(
        `${capitalized(kind.plural)} of ${source}`,
        `the ${kind.plural} of \`${source}\``,
        units.map((unit) =>
            printOutline(
                header(kind, unit, flags),
                [...memberSections(unit.members, KINDS, flags), notesSection(unit.notes)],
                unit.visibility === 'private',
            ),
        ),
    );
}

/** The reports of the units of one kind by their snapshot file: one per source file, for all its units of the kind. */
function reportsOf<Unit extends RegisteredUnit>(kind: ReportKind<Unit>, model: Model): [string, string][] {
    const bySource = new Map<string, Unit[]>();

    kind.units(model).forEach((unit) => bySource.set(unit.source, [...(bySource.get(unit.source) ?? []), unit]));

    return [...bySource].map(([source, units]) => [units[0].file, printReport(kind, source, units, model.flags)]);
}

/**
 * @private
 *
 * The reports of every registered unit of the model by their snapshot file.
 */
export function printRegisteredReports(model: Model): [string, string][] {
    return [...reportsOf(SERVICE_REPORTS, model), ...reportsOf(STORE_REPORTS, model)];
}

/**
 * @private
 *
 * The entries of the registered units in `index.json`, by the plural of their kind.
 */
export function registeredIndexEntries(model: Model): Record<string, Record<string, object>> {
    const entriesOf = <Unit extends RegisteredUnit>({
        plural,
        units,
    }: ReportKind<Unit>): [string, Record<string, object>] => [
        plural,
        Object.fromEntries(units(model).map(({ name, file, visibility }) => [name, { file, visibility }])),
    ];

    return Object.fromEntries([entriesOf(SERVICE_REPORTS), entriesOf(STORE_REPORTS)]);
}
