/**
 * @sw-package framework
 */
import * as helpers from './index';
import { mapOptionsPropKeys } from './map-options-prop-keys';
import { mapPropValue } from './map-prop-value';
import { missingProp } from './missing-prop';
import { removeEvent } from './remove-event';
import { removeProp } from './remove-prop';
import { removeSlot } from './remove-slot';
import { renameEvent } from './rename-event';
import { renameProp } from './rename-prop';
import { renameVModelArgument } from './rename-v-model-argument';
import { slotToItemsProp } from './slot-to-items-prop';
import { slotToProp } from './slot-to-prop';
import { slotToPropComment } from './slot-to-prop-comment';
import {
    componentUsageMessage,
    findObjectVBindAttribute,
    hasObjectVBind,
    manualUsage,
    migration,
    reference,
    usage,
    usageFixesAutomatically,
    withoutUndefined,
} from './shared';
import { createAttribute, createDirectiveAttribute, createRuleApi } from './test-utils';

describe('src/app/deprecation-registry/definitions/helpers', () => {
    describe('index', () => {
        it('re-exports every component usage helper', () => {
            expect(helpers).toEqual(
                expect.objectContaining({
                    mapOptionsPropKeys,
                    mapPropValue,
                    missingProp,
                    removeEvent,
                    removeProp,
                    removeSlot,
                    renameEvent,
                    renameProp,
                    renameVModelArgument,
                    slotToItemsProp,
                    slotToProp,
                    slotToPropComment,
                    reference,
                }),
            );
        });

        it('marks a usage step to run only on converted elements', () => {
            const step = helpers.onlyAfterConversion(helpers.renameProp({ from: 'old', to: 'new' }));

            expect(step).toEqual(expect.objectContaining({ kind: 'rename-prop', onlyAfterConversion: true }));
        });

        it('builds the component replacement step with an automatic fix by default', () => {
            expect(helpers.renameComponent({ from: 'sw-old', to: 'mt-new' })).toEqual({
                kind: 'rename-component',
                from: 'sw-old',
                to: 'mt-new',
                fix: 'auto',
            });
        });
    });

    describe('shared', () => {
        it('drops undefined values deeply and keeps other falsy values', () => {
            expect(
                withoutUndefined({
                    a: undefined,
                    b: null,
                    c: [
                        { d: undefined, e: 0 },
                    ],
                }),
            ).toEqual({
                b: null,
                c: [
                    { e: 0 },
                ],
            });
        });

        it('builds references and migrations without undefined fields', () => {
            expect(reference({ type: 'upgrade', target: 'UPGRADE-6.8.md#x' })).toEqual({
                type: 'upgrade',
                target: 'UPGRADE-6.8.md#x',
            });
            expect(
                migration({
                    id: 'test',
                    deprecatedIn: '6.8.0',
                    removedIn: '6.9.0',
                    description: 'Test.',
                    usage: [],
                    component: undefined,
                }),
            ).toEqual({
                id: 'test',
                deprecatedIn: '6.8.0',
                removedIn: '6.9.0',
                description: 'Test.',
                usage: [],
            });
        });

        it('builds usage steps and defaults manual usage steps to a manual fix', () => {
            expect(usage('member-call', { from: 'a', message: undefined })).toEqual({ kind: 'member-call', from: 'a' });
            expect(manualUsage('member-call', { from: 'a' })).toEqual({ kind: 'member-call', from: 'a', fix: 'manual' });
            expect(manualUsage('member-call', { from: 'a', fix: 'auto' })).toEqual({
                kind: 'member-call',
                from: 'a',
                fix: 'auto',
            });
        });

        it('builds the component usage message from the step message or the deprecated API name', () => {
            const step = helpers.renameProp({ from: 'old', to: 'new' });
            const api = createRuleApi({ usage: step });

            expect(componentUsageMessage(api, step, 'old')).toBe(
                '[mt-test] The "old" API is deprecated. Use "new" instead.',
            );
            expect(componentUsageMessage(api, { ...step, message: 'Custom.' }, 'old', 'More.')).toBe(
                '[mt-test] Custom.\nMore.',
            );
        });

        it('fixes automatically unless the step is manual or fixes are disabled', () => {
            const step = helpers.renameProp({ from: 'old', to: 'new' });

            expect(usageFixesAutomatically(createRuleApi({ usage: step }), step)).toBe(true);
            expect(usageFixesAutomatically(createRuleApi({ usage: step, disabledFix: true }), step)).toBe(false);
            expect(usageFixesAutomatically(createRuleApi({ usage: step }), { ...step, fix: 'manual' })).toBe(false);
        });

        it('finds an object v-bind but no argument v-bind', () => {
            const step = helpers.renameProp({ from: 'old', to: 'new' });
            const objectBind = createDirectiveAttribute('bind');
            const argumentBind = createDirectiveAttribute('bind', 'old');

            const withObjectBind = createRuleApi({ usage: step, attribute: objectBind });
            const withArgumentBind = createRuleApi({ usage: step, attribute: argumentBind });
            const withStaticAttribute = createRuleApi({ usage: step, attribute: createAttribute('old') });

            expect(findObjectVBindAttribute(withObjectBind)).toBe(objectBind);
            expect(hasObjectVBind(withObjectBind)).toBe(true);
            expect(findObjectVBindAttribute(withArgumentBind)).toBeNull();
            expect(hasObjectVBind(withStaticAttribute)).toBe(false);
        });
    });
});
