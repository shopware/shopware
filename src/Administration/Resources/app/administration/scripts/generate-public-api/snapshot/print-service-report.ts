/**
 * @sw-package framework
 *
 * Prints the `.api.md` reports of the services: one per source file that declares services, with one outline
 * per service.
 */
import type { FlagContext } from '../model/flag-states';
import { KINDS } from '../model/members';
import type { ServiceUnit } from '../model/model';
import { memberSections } from './print-members';
import { flagStateMarker, line, markerLabels, notesSection, printOutline, printReportFile } from './report-outline';
import type { Line } from './report-outline';

function header(unit: ServiceUnit, flags: FlagContext): Line[] {
    return [
        line(
            `service ${unit.name}  ${unit.visibility}  ${unit.language}  ${unit.declared}  ${unit.registration}`,
            flagStateMarker(unit.states, flags),
            ...markerLabels(unit.markers),
        ),
        line(`  registered ${unit.registeredIn}`),
        line(`  source ${unit.source}`),
        ...(unit.visibility === 'private' ? [line(`  visibility-source ${unit.visibilitySource}`)] : []),
    ];
}

function printServiceReport(source: string, units: ServiceUnit[], flags: FlagContext): string {
    return printReportFile(
        `Services of ${source}`,
        `the services of \`${source}\``,
        units.map((unit) =>
            printOutline(
                header(unit, flags),
                [...memberSections(unit.members, KINDS, flags), notesSection(unit.notes)],
                unit.visibility === 'private',
            ),
        ),
    );
}

/**
 * @private
 *
 * The reports of the services by their snapshot file: one per source file, for all the services it declares,
 * in the order given.
 */
export function printServiceReports(services: ServiceUnit[], flags: FlagContext): [string, string][] {
    const bySource = new Map<string, ServiceUnit[]>();

    services.forEach((unit) => bySource.set(unit.source, [...(bySource.get(unit.source) ?? []), unit]));

    return [...bySource].map(([source, units]) => [units[0].file, printServiceReport(source, units, flags)]);
}
