/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { RemoveEventStep, RenameEventStep, StepConfig } from '../types';
import { defineUsageStep, hasObjectVOn, reportUsage } from '../shared';

/**
 * Renames a deprecated event listener, keeping its handler.
 *
 * - `@from` → `@to`
 * - an object `v-on` on the element → manual, because the bound object can listen to the old name
 *
 * @example
 * renameEvent({ from: 'update:value', to: 'update:model-value' });
 * // <mt-x @update:value="onChange" /> → <mt-x @update:model-value="onChange" />
 */
export function renameEvent({ fix = 'auto', ...config }: StepConfig<RenameEventStep>): RenameEventStep {
    return defineUsageStep<RenameEventStep>({ kind: 'rename-event', fix, ...config }, (api, step) => {
        const attribute = api.ast.findMatchingEventAttribute(api.node, step.from);

        if (!attribute) {
            return;
        }

        reportUsage(api, {
            node: attribute,
            apiName: step.from,
            replacement: step.to,
            ...(hasObjectVOn(api)
                ? {
                      manualReason: `Object v-on can hide the replacement event. Review the bound listener object and rename "${step.from}" to "${step.to}" manually if needed.`,
                  }
                : {
                      produces: { kind: 'event', name: step.to },
                      fix: (fixer) => fixer.replaceText(attribute.key.argument, step.to),
                  }),
        });
    });
}

/**
 * Removes the listener of an event the replacement component no longer emits.
 *
 * - `@event` → removed
 * - an object `v-on` on the element → manual
 *
 * @example
 * removeEvent({ event: 'base-field-mounted' });
 * // <mt-x @base-field-mounted="onMounted" /> → <mt-x />
 */
export function removeEvent({ fix = 'auto', ...config }: StepConfig<RemoveEventStep>): RemoveEventStep {
    return defineUsageStep<RemoveEventStep>({ kind: 'remove-event', fix, ...config }, (api, step) => {
        const attribute = api.ast.findMatchingEventAttribute(api.node, step.event);

        if (!attribute) {
            return;
        }

        reportUsage(api, {
            node: attribute,
            apiName: step.event,
            ...(hasObjectVOn(api)
                ? {
                      manualReason: `Object v-on can hide this event. Review the bound listener object and remove "${step.event}" manually if needed.`,
                  }
                : { fix: (fixer) => api.removeAttribute(fixer, attribute) }),
        });
    });
}
