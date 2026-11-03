/**
 * @sw-package framework
 */
import {
    codemodRule,
    flagSlotForManualMove,
    mapOptionsPropKeys,
    mapPropValue,
    memberCall,
    missingProp,
    onlyAfterConversion,
    removeAsset,
    removeEvent,
    removeProp,
    removeSlot,
    removeSnippetKey,
    removeTemplateBlock,
    renameCall,
    renameComponent,
    renameEvent,
    renamePackage,
    renameProp,
    renameTemplateBlock,
    renameVModelArgument,
    replaceExtension,
    replaceObjectOption,
    slotToItemsProp,
    slotToProp,
    templateEventUsage,
    upgradeGuide,
} from './index';

// The fixes themselves are covered by the RuleTester fixtures of `no-deprecated-component-usage`.
describe('src/app/deprecation-registry/definitions/helpers', () => {
    it.each([
        ['rename-prop', 'auto', renameProp({ from: 'a', to: 'b' })],
        ['remove-prop', 'auto', removeProp({ prop: 'a' })],
        ['map-prop-value', 'auto', mapPropValue({ prop: 'a', from: 'b', to: 'c' })],
        ['map-options-prop-keys', 'unsafe-auto', mapOptionsPropKeys({ prop: 'options', from: { name: 'label' } })],
        ['missing-prop', 'auto', missingProp({ prop: 'a', value: 'b' })],
        ['remove-slot', 'auto', removeSlot({ slot: 'a' })],
        ['slot-to-prop', 'auto', slotToProp({ slot: 'a', prop: 'b' })],
        ['flag-slot-for-manual-move', 'unsafe-auto', flagSlotForManualMove({ slot: 'a', hint: 'Move it.' })],
        ['slot-to-items-prop', 'unsafe-auto', slotToItemsProp({ prop: 'items', itemComponent: 'sw-tabs-item' })],
        ['rename-event', 'auto', renameEvent({ from: 'a', to: 'b' })],
        ['remove-event', 'auto', removeEvent({ event: 'a' })],
        ['rename-v-model-argument', 'auto', renameVModelArgument({ from: 'value' })],
    ])('builds a %s step with fix level %s and an ESLint check', (kind, fix, step) => {
        expect(step).toEqual(expect.objectContaining({ kind, fix }));
        expect(typeof step.eslint.report).toBe('function');
    });

    it.each([
        ['rename-component', 'auto', renameComponent({ from: 'sw-a', to: 'mt-a' })],
        ['codemod-rule', 'unsafe-auto', codemodRule({ rule: 'r', from: 'sw-a', to: 'mt-a' })],
        ['rename-call', 'unsafe-auto', renameCall({ from: '$tc', to: '$t' })],
        ['replace-object-option', 'auto', replaceObjectOption({ from: 'a', to: 'b' })],
        ['member-call', 'manual', memberCall({ from: 'this.a' })],
        ['replace-extension', 'auto', replaceExtension({ from: ['.png'], to: '.webp' })],
        ['remove-asset', 'manual', removeAsset()],
        ['remove-template-block', 'manual', removeTemplateBlock({ from: 'a' })],
        ['rename-template-block', 'auto', renameTemplateBlock({ from: 'a', to: 'b' })],
        ['rename-template-event', 'auto', templateEventUsage({ component: 'sw-a', from: 'a', to: 'b' })],
        ['remove-snippet-key', 'manual', removeSnippetKey({ from: 'a' })],
        ['rename-package', 'auto', renamePackage({ from: '@a/a', to: '@a/b' })],
    ])('builds a plain %s step with fix level %s', (kind, fix, step) => {
        expect(step).toEqual(expect.objectContaining({ kind, fix }));
    });

    it('keeps a configured fix level and message', () => {
        expect(renameComponent({ from: 'sw-a', to: 'mt-a', fix: 'manual', message: 'Check it.' })).toEqual({
            kind: 'rename-component',
            from: 'sw-a',
            to: 'mt-a',
            fix: 'manual',
            message: 'Check it.',
        });
    });

    it('derives the review comment of a slot that moves to a prop', () => {
        expect(flagSlotForManualMove({ slot: 'default', toProp: 'options' }).hint).toBe(
            'Remove the "default" slot and use the "options" prop instead',
        );
    });

    it('marks a usage step to run only on converted elements', () => {
        expect(onlyAfterConversion(renameProp({ from: 'a', to: 'b' }))).toEqual(
            expect.objectContaining({ kind: 'rename-prop', onlyAfterConversion: true }),
        );
    });

    it('links an upgrade guide heading', () => {
        expect(upgradeGuide('UPGRADE-6.9.md#x')).toEqual({ type: 'upgrade', target: 'UPGRADE-6.9.md#x' });
    });
});
