import { camelize, type App, type ComponentPublicInstance } from 'vue';

/**
 * @sw-package framework
 */

/**
 * @private
 */
export type Deprecation = string | { version: string; comment?: string };

type PropDefinitions = Record<string, { deprecated?: Deprecation } | null>;

/**
 * @private
 *
 * Guards the `deprecated` option of components and props: warns while the major of the removal version
 * is inactive, throws once it is active. A component is guarded whenever it is created, a prop whenever
 * the parent passes it.
 *
 * @example
 * Component.register('sw-example', {
 *     deprecated: { version: 'v6.8.0.0', comment: 'Use "mt-example" instead.' },
 *
 *     props: {
 *         emptyImagePath: {
 *             type: String,
 *             deprecated: { version: 'v6.8.0.0', comment: 'Use "emptyIcon" instead.' },
 *         },
 *     },
 * });
 */
export default {
    install(app: App): void {
        app.mixin({
            beforeCreate(this: ComponentPublicInstance) {
                const { name, deprecated } = this.$options;
                const props = (this.$options.props ?? {}) as PropDefinitions;

                // `$props` holds every declared prop, only the vnode knows which ones the parent passed
                const passedProps = Object.entries(this.$.vnode.props ?? {})
                    .filter(([, value]) => value !== undefined)
                    .map(([prop]) => camelize(prop));

                if (deprecated) {
                    guard(this, deprecated, `The component "${name}" is deprecated`);
                }

                Object.entries(props).forEach(([prop, definition]) => {
                    if (definition?.deprecated && passedProps.includes(prop)) {
                        guard(this, definition.deprecated, `The prop "${prop}" of the component "${name}" is deprecated`);
                    }
                });
            },
        });
    },
};

function guard(component: ComponentPublicInstance, deprecation: Deprecation, subject: string): void {
    const { version, comment } = typeof deprecation === 'object' ? deprecation : { version: deprecation, comment: '' };

    const message = [
        `${subject} and will be removed in Shopware ${version}.`,
        comment,
        `Used in: ${usagePath(component)}`,
    ]
        .filter(Boolean)
        .join('\n');

    Shopware.Feature.triggerDeprecationOrThrow(toMajorFlag(version), message);
}

/**
 * `v6.8.0.0`, `6.8.0` and `6.8` all map to the flag `V6_8_0_0`. A non-string, like `true` from an
 * untyped component, is passed on as it is, so the guard reports it as an unknown flag.
 */
function toMajorFlag(version: unknown): string {
    if (typeof version !== 'string') {
        return String(version);
    }

    const [
        major,
        minor,
        patch = '0',
        build = '0',
    ] = version.replace(/^v/, '').split('.');

    return `V${major}_${minor}_${patch}_${build}`;
}

/**
 * E.g. `sw-page > sw-card > sw-example`. Part of the message, so each usage site is warned about once.
 */
function usagePath(component: ComponentPublicInstance): string {
    const name = component.$options.name ?? '<anonymous>';

    return component.$parent ? `${usagePath(component.$parent)} > ${name}` : name;
}
