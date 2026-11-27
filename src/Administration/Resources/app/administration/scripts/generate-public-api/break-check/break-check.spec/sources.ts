/**
 * @sw-package framework
 *
 * The break check on two versions of the example components, for the specs here.
 */
import type { Model } from '../../model/model';
import { buildModel } from '../../read/build-model';
import { cardTree, exampleTree, OPTIONS } from '../../testing/example-components';
import type { Card, ExampleSources } from '../../testing/example-components';
import { classifyChanges, explainChanges } from '../break-check';
import type { ExplainedFinding, Finding } from '../finding';

/** A string is the template of the card. */
type Sources = ExampleSources | Card | string;

function modelOf(sources: Sources): Model {
    if (typeof sources === 'string') {
        return buildModel(exampleTree({ card: sources }), OPTIONS);
    }

    return buildModel('card' in sources ? exampleTree(sources) : cardTree(sources), OPTIONS);
}

/**
 * @private
 *
 * The findings for a change. A string is the template of the card.
 */
export function check(before: Sources, after: Sources): Finding[] {
    return classifyChanges(modelOf(before), modelOf(after));
}

/**
 * @private
 *
 * The same findings with what a view says about each one beyond the summary.
 */
export function explain(before: Sources, after: Sources): ExplainedFinding[] {
    return explainChanges(modelOf(before), modelOf(after));
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

/**
 * @private
 *
 * One line per finding about the card: its group, its rule and its element below the card.
 */
export function memberSummary(findings: Finding[]): string[] {
    return findings.map(
        ({ group, rule, element }) => `${group}  ${rule}  ${element.replace('component:sw-example-card/', '')}`,
    );
}

/**
 * @private
 *
 * One method per line, as the sources write them: a comment belongs to the member in the line below it.
 */
export function methods(...declarations: string[]): Card {
    return { options: `methods: {\n${declarations.join(',\n')}\n}` };
}

/**
 * @private
 *
 * The card with `markup` inside its root block.
 */
export function template(markup: string): Card {
    return { template: `{% block sw_example_card %}${markup}{% endblock %}` };
}
