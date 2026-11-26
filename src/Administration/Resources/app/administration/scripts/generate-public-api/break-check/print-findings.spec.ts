/**
 * @sw-package framework
 */
import type { Finding } from './finding';
import { printFindings } from './print-findings';

const PARAMETER_RENAMED: Finding = {
    group: 'Info',
    rule: 'method_param_renamed',
    element: 'component:sw-example-card/method:onSave/param:entity',
    states: ['current', 'major'],
    explanation: 'The parameter item is now called entity.',
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

describe('printFindings', () => {
    it('prints the groups in their fixed order and leaves out details a finding does not have', () => {
        expect(printFindings([PARAMETER_RENAMED, BLOCK_REMOVED])).toBe(
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
                '  method_param_renamed  component:sw-example-card/method:onSave/param:entity  current, major',
                '    The parameter item is now called entity.',
                '',
            ].join('\n'),
        );
    });

    it('prints an announced change, a private change and the extend children a finding reaches', () => {
        const allowed: Finding = { ...BLOCK_REMOVED, group: 'Allowed', rule: 'deprecated_removal', fix: undefined };
        const inherited: Finding = {
            ...BLOCK_REMOVED,
            group: 'Private',
            alsoAffects: ['sw-example-tile', 'sw-example-row'],
        };

        expect(printFindings([PARAMETER_RENAMED, inherited, allowed])).toBe(
            [
                'Public API check passed: no breaks.',
                '',
                'Allowed (1)',
                '  deprecated_removal  component:sw-example-card/block:sw_example_card_title  major',
                '    The block is gone in the major state.',
                '    Possible rename: sw_example_card_headline.',
                '    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
                '',
                'Private (1)',
                '  block_missing  component:sw-example-card/block:sw_example_card_title  major',
                '    The block is gone in the major state.',
                '    Fix: keep the block.',
                '    Possible rename: sw_example_card_headline.',
                '    Also affects: sw-example-tile, sw-example-row.',
                '    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
                '',
                'Info (1)',
                '  method_param_renamed  component:sw-example-card/method:onSave/param:entity  current, major',
                '    The parameter item is now called entity.',
                '',
            ].join('\n'),
        );
    });

    it('passes without findings', () => {
        expect(printFindings([])).toBe('Public API check passed: no breaks.\n');
    });
});
