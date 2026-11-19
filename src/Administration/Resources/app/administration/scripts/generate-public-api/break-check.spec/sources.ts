/**
 * @sw-package framework
 *
 * The break check on two versions of the example components, for the specs here.
 */
import { classifyChanges } from '../break-check';
import { exampleTree, OPTIONS } from '../example-components';
import type { ExampleSources } from '../example-components';
import type { Finding } from '../findings';
import { buildModel } from '../model';

/**
 * @private
 *
 * The findings for a change. A string is the template of the card.
 */
export function check(before: ExampleSources | string, after: ExampleSources | string, headVersion = '6.7'): Finding[] {
    const model = (sources: ExampleSources | string) =>
        buildModel(exampleTree(typeof sources === 'string' ? { card: sources } : sources), OPTIONS);

    return classifyChanges(model(before), model(after), { headVersion });
}

/**
 * @private
 *
 * One line per finding: its group, its rule, its element below `component:` and its flag states.
 */
export function summary(findings: Finding[]): string[] {
    return findings.map(
        ({ group, rule, element, states }) =>
            `${group}  ${rule}  ${element.replace('component:', '')}  ${states.join(', ')}`,
    );
}
