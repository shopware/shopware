/**
 * @sw-package framework
 */
import {
    addPropWhenMissing,
    booleanPropToPropValue,
    childElementsToArrayProp,
    componentMembers,
    componentMigration,
    firstFoundOf,
    flagSlotForManualMove,
    fromAttribute,
    fromProp,
    fromSlot,
    fromText,
    invertBooleanProp,
    memberCall,
    propFromSource,
    removeAsset,
    removeEvent,
    removeProp,
    removeSlot,
    removeSnippetKey,
    removeTemplateBlock,
    removeTemplateBlocks,
    renameCall,
    renameComponent,
    renameEvent,
    renamePackage,
    renameArrayPropKeys,
    renameProp,
    renameTemplateBlock,
    renameTemplateBlocks,
    renameVModelArgument,
    replaceExtension,
    replaceObjectOption,
    replaceProp,
    replacePropValue,
    routerLinkClickHandler,
    slotTextToProp,
    templateEventUsage,
    upgradeGuide,
} from './index';

// The fixes themselves are covered by the RuleTester fixtures of `no-deprecated-component-usage`.
describe('src/app/deprecation-registry/definitions/helpers', () => {
    it.each([
        ['rename-prop', 'auto', renameProp({ from: 'a', to: 'b' })],
        ['invert-boolean-prop', 'auto', invertBooleanProp({ from: 'a', to: 'b' })],
        ['boolean-prop-to-prop-value', 'auto', booleanPropToPropValue({ fromProp: 'a', toProp: 'b', withValue: '1' })],
        ['remove-prop', 'auto', removeProp({ prop: 'a' })],
        ['replace-prop', 'auto', replaceProp({ prop: 'a', with: routerLinkClickHandler })],
        ['replace-prop-value', 'auto', replacePropValue({ prop: 'a', fromValue: 'b', toValue: 'c' })],
        ['rename-array-prop-keys', 'auto', renameArrayPropKeys({ prop: 'options', renameKeys: { name: 'label' } })],
        ['add-prop-when-missing', 'auto', addPropWhenMissing({ prop: 'a', value: 'b' })],
        ['remove-slot', 'auto', removeSlot({ slot: 'a' })],
        ['slot-text-to-prop', 'auto', slotTextToProp({ fromSlot: 'a', toProp: 'b' })],
        ['flag-slot-for-manual-move', 'manual', flagSlotForManualMove({ slot: 'a', hint: 'Move it.' })],
        [
            'child-elements-to-array-prop',
            'auto',
            childElementsToArrayProp({ childElement: 'sw-tabs-item', arrayProp: 'items', eachItem: { label: fromText() } }),
        ],
        ['prop-from-source', 'auto', propFromSource({ toProp: 'a', source: fromProp('b') })],
        ['rename-event', 'auto', renameEvent({ from: 'a', to: 'b' })],
        ['remove-event', 'auto', removeEvent({ event: 'a' })],
        ['rename-v-model-argument', 'auto', renameVModelArgument({ from: 'value' })],
    ])('builds a %s step with fix level %s and an ESLint check', (kind, fix, step) => {
        expect(step).toEqual(expect.objectContaining({ kind, fix }));
        expect(typeof step.eslint.report).toBe('function');
    });

    it.each([
        ['rename-component', 'auto', renameComponent({ from: 'sw-a', to: 'mt-a' })],
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
            'Remove the "default" slot and use the "options" prop instead.',
        );
    });

    it('limits the onConvert steps after the rename to converted elements', () => {
        const migration = componentMigration({
            id: 'component.sw-x',
            component: 'sw-x',
            replacement: 'mt-x',
            deprecatedIn: '6.8.0',
            removedIn: '6.9.0',
            description: 'x',
            onConvert: [
                renameComponent({ from: 'sw-x', to: 'mt-x' }),
                renameProp({ from: 'a', to: 'b' }),
            ],
            onEveryUsage: [renameProp({ from: 'c', to: 'd' })],
        });

        expect(migration.usage).toEqual([
            expect.not.objectContaining({ onlyAfterConversion: true }),
            expect.objectContaining({ from: 'a', onlyAfterConversion: true }),
            expect.not.objectContaining({ onlyAfterConversion: true }),
        ]);
    });

    it('describes where a value comes from', () => {
        expect(firstFoundOf([fromSlot('icon', { childElement: ['mt-icon'], attribute: 'name' }), fromProp('icon')])).toEqual(
            {
                from: 'first-found-of',
                sources: [
                    { from: 'slot', slot: 'icon', childElement: ['mt-icon'], attribute: 'name' },
                    { from: 'prop', prop: 'icon' },
                ],
            },
        );
        expect(fromAttribute('value', { required: true })).toEqual({
            from: 'attribute',
            attribute: 'value',
            required: true,
        });
    });

    it('links an upgrade guide heading', () => {
        expect(upgradeGuide('UPGRADE-6.9.md#x')).toEqual({ type: 'upgrade', target: 'UPGRADE-6.9.md#x' });
    });

    it('builds one rename step per block, limited to shared names and reviewing the active slot', () => {
        expect(
            renameTemplateBlocks({
                components: ['sw-a', 'sw-a-create'],
                sharedNames: ['shared_block'],
                activeSlot: ['tab_block'],
                blocks: { shared_block: 'sw_a_block', tab_block: 'sw_a_mt_tab_block' },
            }),
        ).toEqual([
            renameTemplateBlock({
                from: 'shared_block',
                to: 'sw_a_block',
                components: ['sw-a', 'sw-a-create'],
                onlyInComponents: true,
            }),
            renameTemplateBlock({
                from: 'tab_block',
                to: 'sw_a_mt_tab_block',
                components: ['sw-a', 'sw-a-create'],
                fix: 'unsafe-auto',
                message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
            }),
        ]);
    });

    it('builds one remove step per block, with the block to move to when there is one', () => {
        expect(
            removeTemplateBlocks({ components: ['sw-a'], blocks: { sw_a_title: 'sw_a_header', sw_a_banner: null } }),
        ).toEqual([
            removeTemplateBlock({ from: 'sw_a_title', moveTo: 'sw_a_header', components: ['sw-a'] }),
            removeTemplateBlock({ from: 'sw_a_banner', components: ['sw-a'] }),
        ]);
    });

    it('builds one component member step per member', () => {
        expect(componentMembers({ components: ['sw-a'], members: ['isLoading', 'config'], to: 'schema' })).toEqual([
            { kind: 'component-member', fix: 'manual', components: ['sw-a'], member: 'isLoading', to: 'schema' },
            { kind: 'component-member', fix: 'manual', components: ['sw-a'], member: 'config', to: 'schema' },
        ]);
    });
});
