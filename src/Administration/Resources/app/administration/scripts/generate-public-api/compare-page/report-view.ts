/**
 * @sw-package framework
 *
 * Shows the findings of a break check on the page: the verdict, a switch per group, a text filter, the
 * findings, and the summary as the command prints it.
 */
import { FINDING_GROUPS } from '../break-check/finding';
import type { Finding, FindingGroup } from '../break-check/finding';
import { findingDetails, printFindings } from '../break-check/print-findings';

/** @private */
export interface ReportOptions {
    /** The page of the source line that a finding names as `<file>:<line>`. */
    sourceUrl: (at: string) => string;
}

/** Changes to private elements are never public API, and a large change has hundreds of them. */
const HIDDEN_AT_FIRST: readonly FindingGroup[] = ['Private'];

type Child = Node | string;

function element<Tag extends keyof HTMLElementTagNameMap>(
    document: Document,
    tag: Tag,
    className: string,
    children: readonly Child[] = [],
): HTMLElementTagNameMap[Tag] {
    const node = document.createElement(tag);

    node.className = className;
    node.append(...children);

    return node;
}

function findingItem(document: Document, finding: Finding, { sourceUrl }: ReportOptions): HTMLLIElement {
    const item = element(document, 'li', 'finding', [
        element(document, 'div', 'finding-head', [
            element(document, 'span', 'finding-rule', [finding.rule]),
            element(document, 'code', 'finding-element', [finding.element]),
            element(document, 'span', 'finding-states', [finding.states.join(', ')]),
        ]),
        ...findingDetails(finding).map((detail) => element(document, 'p', 'finding-detail', [detail])),
    ]);

    item.dataset.group = finding.group;

    if (finding.at !== undefined) {
        const link = element(document, 'a', 'finding-source', [finding.at]);

        link.href = sourceUrl(finding.at);
        link.target = '_blank';
        link.rel = 'noopener';
        item.append(link);
    }

    return item;
}

function matches(finding: Finding, query: string): boolean {
    const text = [
        finding.rule,
        finding.element,
        ...findingDetails(finding),
    ]
        .join(' ')
        .toLowerCase();

    return query
        .toLowerCase()
        .split(/\s+/)
        .every((word) => text.includes(word));
}

/**
 * @private
 *
 * Replaces the content of `container` with the report of `findings`.
 */
export function renderReport(container: HTMLElement, findings: Finding[], options: ReportOptions): void {
    const document = container.ownerDocument;
    const summary = printFindings(findings);
    const shown = new Set(FINDING_GROUPS.filter((group) => !HIDDEN_AT_FIRST.includes(group)));
    const filter = element(document, 'input', 'report-filter');
    const count = element(document, 'p', 'report-count');
    const list = element(document, 'ol', 'report-findings');

    const update = (): void => {
        const visible = findings.filter(({ group }) => shown.has(group)).filter((finding) => matches(finding, filter.value));

        count.textContent = `Showing ${visible.length} of ${findings.length} findings.`;
        list.replaceChildren(...visible.map((finding) => findingItem(document, finding, options)));
    };

    const groupSwitches = FINDING_GROUPS.map((group) => ({
        group,
        size: findings.filter((finding) => finding.group === group).length,
    }))
        .filter(({ size }) => size > 0)
        .map(({ group, size }) => {
            const button = element(document, 'button', 'report-group', [`${group} ${size}`]);

            button.type = 'button';
            button.dataset.group = group;
            button.setAttribute('aria-pressed', String(shown.has(group)));
            button.onclick = () => {
                if (!shown.delete(group)) {
                    shown.add(group);
                }

                button.setAttribute('aria-pressed', String(shown.has(group)));
                update();
            };

            return button;
        });

    filter.type = 'search';
    filter.placeholder = 'Filter by component, block, rule or text';
    filter.setAttribute('aria-label', 'Filter the findings');
    filter.oninput = update;

    container.replaceChildren(
        // The first line of the summary is the verdict.
        element(document, 'h2', 'report-verdict', [summary.slice(0, summary.indexOf('\n'))]),
        element(document, 'div', 'report-groups', groupSwitches),
        filter,
        count,
        list,
        element(document, 'details', 'report-summary', [
            element(document, 'summary', '', ['Summary as the command prints it']),
            element(document, 'pre', '', [summary]),
        ]),
    );
    update();
}
