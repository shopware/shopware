/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import type { ComponentPropsOptions } from 'vue';
import { defineComponent } from 'vue';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';

/**
 * @private
 *
 * Defines a component whose setup goes through `createExtendableSetup()`, the way a hand-written
 * Composition API component uses the extension system.
 */
export function defineExtendable(
    options: { name: string; template: string; props?: ComponentPropsOptions; emits?: string[] },
    originalSetup: (props: any) => { public?: Record<string, unknown>; private?: Record<string, unknown> },
) {
    return defineComponent({
        template: options.template,
        props: options.props ?? {},
        emits: options.emits ?? [],
        setup: (props, context) =>
            createExtendableSetup({ props, context, name: options.name }, originalSetup as never) as Record<string, any>,
    });
}
