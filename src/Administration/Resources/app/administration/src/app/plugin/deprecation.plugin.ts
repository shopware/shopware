import { getCurrentInstance } from 'vue';
import type { App, ComponentPublicInstance } from 'vue';
import {
    formatComponentUsageWarning,
    getComponentApiMigration,
    getComponentUsageMigration,
    getRuntimeDeprecatedProps,
} from 'src/app/deprecation-registry';

const { warn } = Shopware.Utils.debug;

type DeprecatedTag =
    | string
    | {
          version: string;
          comment?: string;
      };

type DeprecatedPropDefinition = {
    deprecated: DeprecatedTag;
    default?: unknown | (() => unknown);
};

type DeprecatedPropMap = Record<string, DeprecatedPropDefinition>;

type DeprecationInformation = {
    version: string;
    comment: string;
};

type ComponentWithDeprecation = ComponentPublicInstance & {
    $options: ComponentPublicInstance['$options'] & {
        deprecated?: DeprecatedTag;
        name?: string;
    };
    $parent?: ComponentWithDeprecation | null;
    $props: Record<string, unknown>;
    $watch: ComponentPublicInstance['$watch'];
};

type RuntimeDeprecationUsage = ReturnType<typeof getRuntimeDeprecatedProps>[number];

function hasOwn(object: Record<string, unknown>, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(object, key);
}

function propNameToAttributeName(propName: string): string {
    return propName.replace(/[A-Z]/g, (letter: string) => `-${letter.toLowerCase()}`);
}

function runtimePropWasProvided(runtimeProp: string | undefined, usedProps: Record<string, unknown>): boolean {
    if (!runtimeProp) {
        return false;
    }

    return hasOwn(usedProps, runtimeProp) || hasOwn(usedProps, propNameToAttributeName(runtimeProp));
}

function usageWasRuntimeDetected(
    usage: RuntimeDeprecationUsage,
    usedProps: Record<string, unknown>,
    componentName: string,
): boolean {
    const runtimeProp = usage.runtimeProp;

    return (
        usage.runtime?.detect?.({
            usedProps,
            componentName,
        }) ?? (runtimeProp ? hasOwn(usedProps, runtimeProp) : false)
    );
}

function getRuntimeUsageWarningKey(usage: RuntimeDeprecationUsage): string {
    return [
        usage.kind,
        usage.runtimeProp,
        usage.prop,
        usage.from,
        usage.to,
    ]
        .filter((entry) => typeof entry === 'string')
        .join(':');
}

/**
 * @sw-package framework
 *
 * @private
 * This plugin allows you to generate deprecations for components and properties.
 *
 * Usage in component:
 * // @deprecated tag:v6.4.0
 * {
 *     name: 'example-component',
 *     deprecated: '6.4.0'
 * }
 *
 * or
 *
 * // @deprecated tag:v6.4.0
 * {
 *     name: 'example-component',
 *     deprecated: {
 *         version: '6.4.0',
 *         comment: 'Insert additional information in comments'
 *     }
 * }
 *
 * Usage in properties:
 *
 * // @deprecated tag:v6.4.0
 * {
 *     name: 'example-component',
 *     props: {
 *         exampleProp: {
 *             type: String,
 *             required: false,
 *             default: 'Default value',
 *             deprecated: '6.4.0'
 *         }
 *     }
 * }
 *
 * or
 *
 * // @deprecated tag:v6.4.0
 * {
 *     name: 'example-component',
 *     props: {
 *         exampleProp: {
 *             type: String,
 *             required: false,
 *             default: 'Default value',
 *             deprecated: {
 *                  version: '6.4.0',
 *                  comment: 'Insert additional information in comments'
 *             }
 *         }
 *     }
 * }
 */
class DeprecationPlugin {
    pluginInstalled: boolean = false;

    runtimeWarnings: Set<string> = new Set();

    /**
     * Installs the Vue Plugin
     *
     * @returns {boolean} is successfully installed
     */
    install(Vue: App): boolean {
        const _this = this;

        if (this.pluginInstalled) {
            warn('Deprecation Plugin', 'This plugin is already installed');
            return false;
        }

        Vue.mixin({
            created() {
                /**
                 * This could break with any minor version of Vue as it's concidered interanl api.
                 */
                const _instance = getCurrentInstance();
                if (!_instance) return;

                const { props } = _instance.type;
                const propsData = _instance.props;
                const rawPropsData = _instance.vnode.props ?? {};

                const deprecatedProps = _this.getDeprecatedProps(props);

                const usedDeprecationProps = _this.getUsedProps(propsData, deprecatedProps);
                const component = this as ComponentWithDeprecation;
                const componentDeprecationInformation = _this.getComponentDeprecationInformation(component);

                _this.throwComponentDeprecationInformationErrors(component, componentDeprecationInformation);
                _this.throwPropsDeprecationErrors(component, usedDeprecationProps);
                _this.throwRegistryPropsDeprecationErrors(component, rawPropsData);
                _this.watchRegistryPropsDeprecationErrors(component, rawPropsData);
            },
        });

        this.pluginInstalled = true;

        return true;
    }

    /**
     * Get the information from the deprecation tag in the component.
     *
     */
    getComponentDeprecationInformation(component: ComponentWithDeprecation): DeprecationInformation | null {
        const deprecatedTag = component.$options.deprecated;

        if (!deprecatedTag) {
            return null;
        }

        let version = '';
        let comment = '';

        if (typeof deprecatedTag === 'string') {
            version = deprecatedTag;
        }

        if (typeof deprecatedTag === 'object' && deprecatedTag !== null) {
            version = deprecatedTag.version;
            comment = deprecatedTag.comment ?? '';
        }

        return {
            version,
            comment,
        };
    }

    /**
     * Get all deprecated props of the component.
     *
     */
    getDeprecatedProps(props: unknown): DeprecatedPropMap {
        if (typeof props !== 'object' || props === null) {
            return {};
        }

        return Object.entries(props).reduce(
            (
                acc,
                [
                    key,
                    value,
                ],
            ) => {
                const propDefinition = value as Partial<DeprecatedPropDefinition>;

                if (propDefinition.deprecated) {
                    acc[key] = propDefinition as DeprecatedPropDefinition;
                }

                return acc;
            },
            {} as DeprecatedPropMap,
        );
    }

    /**
     * Returns the deprecated props which are in the usedProps
     *
     */
    getUsedProps(usedProps: Record<string, unknown>, deprecatedProps: DeprecatedPropMap): Record<string, DeprecatedTag> {
        return Object.entries(deprecatedProps).reduce(
            (
                acc,
                [
                    propKey,
                    prop,
                ],
            ) => {
                // The deprecated property exists in the current instance props
                if (hasOwn(usedProps, propKey)) {
                    // If the deprecated property has a default?
                    // Then it will also be in the current props with the default value
                    if (hasOwn(prop as unknown as Record<string, unknown>, 'default')) {
                        // Only add the prop to the used deprecated props if the value differs from the default
                        // Prop default function
                        if (typeof prop.default === 'function' && prop.default() !== usedProps[propKey]) {
                            acc[propKey] = prop.deprecated;
                            return acc;
                        }

                        // Prop default scalar value
                        if (prop.default !== usedProps[propKey]) {
                            acc[propKey] = prop.deprecated;
                            return acc;
                        }

                        return acc;
                    }

                    acc[propKey] = prop.deprecated;
                }

                return acc;
            },
            {} as Record<string, DeprecatedTag>,
        );
    }

    /**
     * Throw an error for each prop which is deprecated and used from another component
     *
     */
    throwPropsDeprecationErrors(component: ComponentWithDeprecation, deprecationProps: Record<string, DeprecatedTag>): void {
        const componentTrace = this.getComponentTrace(component);
        const componentName = component.$options.name ?? '';

        Object.entries(deprecationProps).forEach(
            ([
                propName,
                deprecationValue,
            ]) => {
                const deprecationVersion =
                    typeof deprecationValue === 'string' ? deprecationValue : deprecationValue.version;
                const registryMigration = getComponentUsageMigration(componentName, (usage) => {
                    const runtimeDetected = usage.runtime?.detect?.({
                        usedProps: {
                            [propName]: true,
                        },
                        componentName,
                    });

                    return (
                        runtimeDetected ||
                        usage.runtimeProp === propName ||
                        usage.from === propName ||
                        usage.prop === propName
                    );
                });

                if (registryMigration) {
                    const warningKey = [
                        registryMigration.migration.id,
                        componentName,
                        propName,
                        componentTrace,
                    ].join('|');

                    if (this.runtimeWarnings.has(warningKey)) {
                        return;
                    }

                    this.runtimeWarnings.add(warningKey);

                    let registryWarningText = formatComponentUsageWarning(componentName, registryMigration.usage);

                    if (registryMigration.migration.references?.length) {
                        const references = registryMigration.migration.references
                            .map((reference) => `${reference.type}: ${reference.target}`)
                            .join('\n');

                        registryWarningText += `\n\nReferences:\n${references}`;
                    }

                    warn(componentName, registryWarningText);
                    warn(componentName, componentTrace);
                    return;
                }

                let warningText = `The component "${componentName}" was used with the deprecated property "${propName}".`;
                warningText += ` The property will be removed in Shopware ${deprecationVersion} \n`;

                if (typeof deprecationValue !== 'string' && deprecationValue.comment) {
                    warningText += `\n ${deprecationValue.comment}`;
                }

                warn(componentName, warningText);
                warn(componentName, componentTrace);
            },
        );
    }

    /**
     * Report deprecated component API props from the shared registry.
     * This covers dynamic prop bags such as v-bind where static analysis cannot
     * see the actual object shape.
     *
     */
    throwRegistryPropsDeprecationErrors(component: ComponentWithDeprecation, usedProps: Record<string, unknown>): void {
        const componentName = component.$options.name ?? '';
        const deprecatedProps = getRuntimeDeprecatedProps(componentName);

        if (!deprecatedProps.length) {
            return;
        }

        const componentTrace = this.getComponentTrace(component);

        deprecatedProps.forEach((usage) => {
            if (!usageWasRuntimeDetected(usage, usedProps, componentName)) {
                return;
            }

            const warningKey = [
                usage.migration.id,
                componentName,
                getRuntimeUsageWarningKey(usage),
                componentTrace,
            ].join('|');

            if (this.runtimeWarnings.has(warningKey)) {
                return;
            }

            this.runtimeWarnings.add(warningKey);

            let warningText = formatComponentUsageWarning(componentName, usage);

            if (usage.migration.references?.length) {
                const references = usage.migration.references
                    .map((reference) => `${reference.type}: ${reference.target}`)
                    .join('\n');

                warningText += `\n\nReferences:\n${references}`;
            }

            warn(componentName, warningText);
            warn(componentName, componentTrace);
        });
    }

    /**
     * Watch registry-backed deprecated props that were explicitly provided.
     * Raw vnode props are used as the opt-in signal so default prop values do
     * not create warnings.
     *
     */
    watchRegistryPropsDeprecationErrors(component: ComponentWithDeprecation, usedProps: Record<string, unknown>): void {
        const componentName = component.$options.name ?? '';
        const deprecatedProps = getRuntimeDeprecatedProps(componentName);

        deprecatedProps.forEach((usage) => {
            const runtimeProp = usage.runtimeProp;

            if (!runtimeProp || !runtimePropWasProvided(runtimeProp, usedProps)) {
                return;
            }

            component.$watch(
                () => component.$props[runtimeProp],
                (value) => {
                    this.throwRegistryPropsDeprecationErrors(component, {
                        [runtimeProp]: value,
                    });
                },
            );
        });
    }

    /**
     * Throw an error with trace with the given deprecationInformation
     *
     */
    throwComponentDeprecationInformationErrors(
        component: ComponentWithDeprecation,
        deprecationInformation: DeprecationInformation | null,
    ): void {
        if (!deprecationInformation) {
            return;
        }

        const { version, comment } = deprecationInformation;
        const componentName = component.$options.name ?? '';
        const migration = getComponentApiMigration(componentName);

        if (migration) {
            return;
        }

        let warningText = `The component "${componentName}" is deprecated and will be removed in Shopware ${version} \n`;

        warn(componentName, warningText + comment);
        warn(componentName, this.getComponentTrace(component));
    }

    /**
     * Creates a component trace string
     *
     */
    getComponentTrace(component: ComponentWithDeprecation): string {
        const trace: string[] = [];

        let actualComponent: ComponentWithDeprecation = component;

        while (actualComponent.$parent) {
            trace.push(actualComponent.$options.name ?? '');

            actualComponent = actualComponent.$parent;
        }

        return trace.reduce((acc, componentName, index) => {
            if (index !== 0) {
                acc += '     ';
            }

            [...Array(index)].forEach(() => {
                acc += ' ';
            });

            acc += `${componentName} \n`;

            return acc;
        }, '\n --> ');
    }
}

export default new DeprecationPlugin();
