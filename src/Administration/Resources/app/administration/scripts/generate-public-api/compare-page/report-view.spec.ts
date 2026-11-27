/**
 * @sw-package framework
 * @jest-environment jsdom
 */
import type { Finding } from '../break-check/finding';
import { printFindings } from '../break-check/print-findings';
import { renderReport } from './report-view';

const BLOCK_REMOVED: Finding = {
    group: 'Break',
    rule: 'block_missing',
    element: 'component:sw-example-card/block:sw_example_card_title',
    states: ['major'],
    explanation: 'The block is gone in the major state.',
    fix: 'keep the block.',
    possibleRename: 'sw_example_card_headline',
    alsoAffects: ['sw-example-tile'],
    at: 'src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
};

const METHOD_ADDED: Finding = {
    group: 'Addition',
    rule: 'method_added',
    element: 'component:sw-example-grid/method:onPageChange',
    states: [
        'current',
        'major',
    ],
    explanation: 'A new public method that plugins can rely on from now on.',
};

const PRIVATE_CHANGED: Finding = {
    group: 'Private',
    rule: 'private_changed',
    element: 'component:sw-example-menu/block:sw_example_menu_item',
    states: ['current'],
    explanation: 'The block is private.',
};

const FINDINGS = [
    BLOCK_REMOVED,
    METHOD_ADDED,
    PRIVATE_CHANGED,
];

describe('renderReport', () => {
    function report(findings: Finding[] = FINDINGS) {
        const container = document.createElement('section');

        renderReport(container, findings, { sourceUrl: (at) => `https://example.org/${at}` });

        return {
            container,
            elements: () => Array.from(container.querySelectorAll('.finding-element')).map((node) => node.textContent),
            group: (name: string) => {
                const button = Array.from(container.querySelectorAll('button')).find((one) =>
                    one.textContent?.startsWith(name),
                );

                if (button === undefined) {
                    throw new Error(`The report has no switch for ${name}.`);
                }

                return button;
            },
            filterBy: (text: string) => {
                const filter = container.querySelector('input');

                if (filter === null) {
                    throw new Error('The report has no filter.');
                }

                filter.value = text;
                filter.dispatchEvent(new Event('input'));
            },
        };
    }

    it('shows the verdict and every finding but the private ones', () => {
        const { container, elements } = report();

        expect(container.querySelector('h2')?.textContent).toBe('Public API check failed: 1 unannounced break.');
        expect(elements()).toEqual([
            BLOCK_REMOVED.element,
            METHOD_ADDED.element,
        ]);
        expect(container.textContent).toContain('Showing 2 of 3 findings.');
    });

    it('shows what a finding says and links its source line', () => {
        const { container } = report([BLOCK_REMOVED]);
        const link = container.querySelector('a');

        expect(container.textContent).toContain('block_missing');
        expect(container.textContent).toContain('The block is gone in the major state.');
        expect(container.textContent).toContain('Fix: keep the block.');
        expect(container.textContent).toContain('Possible rename: sw_example_card_headline.');
        expect(container.textContent).toContain('Also affects: sw-example-tile.');
        expect(link?.textContent).toBe(BLOCK_REMOVED.at);
        expect(link?.href).toBe(`https://example.org/${BLOCK_REMOVED.at}`);
    });

    it('offers a switch per group that has findings, with the private group off', () => {
        const { container, group } = report();

        expect(Array.from(container.querySelectorAll('button')).map((button) => button.textContent)).toEqual([
            'Break 1',
            'Addition 1',
            'Private 1',
        ]);
        expect(group('Break').getAttribute('aria-pressed')).toBe('true');
        expect(group('Private').getAttribute('aria-pressed')).toBe('false');
    });

    it('shows and hides the findings of a group with its switch', () => {
        const { elements, group } = report();

        group('Private').click();
        group('Break').click();

        expect(elements()).toEqual([
            METHOD_ADDED.element,
            PRIVATE_CHANGED.element,
        ]);
        expect(group('Break').getAttribute('aria-pressed')).toBe('false');
    });

    it('keeps the findings that contain every word of the filter', () => {
        const { container, elements, filterBy } = report();

        filterBy('EXAMPLE-card  gone');

        expect(elements()).toEqual([BLOCK_REMOVED.element]);
        expect(container.textContent).toContain('Showing 1 of 3 findings.');
    });

    it('holds the summary as the command prints it', () => {
        const { container } = report();

        expect(container.querySelector('pre')?.textContent).toBe(printFindings(FINDINGS));
    });
});
