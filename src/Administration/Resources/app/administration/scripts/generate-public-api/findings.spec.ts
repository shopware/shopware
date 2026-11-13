/**
 * @sw-package framework
 */
import { formatFindings } from './findings';
import type { Finding } from './findings';

const CLASS_REMOVED: Finding = {
    group: 'Info',
    rule: 'class_missing',
    element: 'component:sw-example-card/class:sw-example-card__title',
    states: ['current', 'major'],
    explanation: 'The CSS class sw-example-card__title is no longer in the template.',
};

const BLOCK_REMOVED: Finding = {
    group: 'Break',
    rule: 'block_missing',
    element: 'component:sw-example-card/block:sw_example_card_title',
    states: ['major'],
    explanation: 'The block is gone in the major state.',
    fix: 'keep the block.',
    possibleRename: 'sw_example_card_headline',
    at: 'src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
};

describe('formatFindings', () => {
    it('prints the groups in their fixed order and leaves out details a finding does not have', () => {
        expect(formatFindings([CLASS_REMOVED, BLOCK_REMOVED])).toBe(
            [
                'Public API check failed: 1 unannounced break.',
                '',
                'Break (1)',
                '  block_missing  component:sw-example-card/block:sw_example_card_title  major',
                '    The block is gone in the major state.',
                '    Fix: keep the block.',
                '    Possible rename: sw_example_card_headline.',
                '    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
                '',
                'Info (1)',
                '  class_missing  component:sw-example-card/class:sw-example-card__title  current, major',
                '    The CSS class sw-example-card__title is no longer in the template.',
                '',
            ].join('\n'),
        );
    });

    it('passes without findings', () => {
        expect(formatFindings([])).toBe('Public API check passed: no breaks.\n');
    });
});
