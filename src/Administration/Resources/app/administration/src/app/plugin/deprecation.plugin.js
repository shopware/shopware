import { getCurrentInstance } from 'vue';

/**
 * @sw-package framework
 *
 * @private
 * This plugin guards deprecated components and properties at the boundary where they are used: the
 * `created()` hook of the instance that was mounted, or that received the deprecated prop. Before the
 * related major it warns; once the major flag is active it throws, so a missed migration in core or in
 * an extension surfaces instead of being carried over silently.
 *
 * See adr/2026-08-10-administration-javascript-deprecation-guards.md.
 *
 * Usage in component:
 * // @deprecated tag:v6.8.0
 * {
 *     name: 'example-component',
 *     deprecated: 'v6.8.0.0'
 * }
 *
 * or
 *
 * // @deprecated tag:v6.8.0
 * {
 *     name: 'example-component',
 *     deprecated: {
 *         version: 'v6.8.0.0',
 *         comment: 'Insert additional information in comments'
 *     }
 * }
 *
 * Usage in properties:
 *
 * // @deprecated tag:v6.8.0
 * {
 *     name: 'example-component',
 *     props: {
 *         exampleProp: {
 *             type: String,
 *             required: false,
 *             default: 'Default value',
 *             deprecated: 'v6.8.0.0'
 *         }
 *     }
 * }
 *
 * or
 *
 * // @deprecated tag:v6.8.0
 * {
 *     name: 'example-component',
 *     props: {
 *         exampleProp: {
 *             type: String,
 *             required: false,
 *             default: 'Default value',
 *             deprecated: {
 *                  version: 'v6.8.0.0',
 *                  comment: 'Insert additional information in comments'
 *             }
 *         }
 *     }
 * }
 */
class DeprecationPlugin {
    /**
     * Vue calls `install` once per app, and every test mount creates its own app, so installing again
     * is normal rather than a mistake.
     */
    installedApps = new WeakSet();

    /**
     * Installs the Vue Plugin
     *
     * @param Vue {Vue}
     * @returns {boolean} is successfully installed
     */
    install(Vue) {
        const _this = this;

        if (this.installedApps.has(Vue)) {
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

                const deprecatedProps = _this.getDeprecatedProps(props);

                const usedDeprecationProps = _this.getUsedProps(propsData, deprecatedProps);
                const componentDeprecationInformation = _this.getComponentDeprecationInformation(this);

                _this.throwComponentDeprecationInformationErrors(this, componentDeprecationInformation);
                _this.throwPropsDeprecationErrors(this, usedDeprecationProps);
            },
        });

        this.installedApps.add(Vue);

        return true;
    }

    /**
     * Get the information from the deprecation tag in the component.
     *
     * @param component {Component}
     * @returns {null|{comment: string, version: string}}
     */
    getComponentDeprecationInformation(component) {
        const deprecatedTag = component.$options.deprecated;

        if (!deprecatedTag) {
            return null;
        }

        let version = '';
        let comment = '';

        if (typeof deprecatedTag === 'string') {
            version = deprecatedTag;
        }

        if (typeof deprecatedTag === 'object') {
            version = deprecatedTag.version;
            comment = deprecatedTag.comment;
        }

        return {
            version,
            comment,
        };
    }

    /**
     * Get all deprecated props of the component.
     *
     * @param props
     * @returns {{}}
     */
    getDeprecatedProps(props) {
        if (typeof props !== 'object') {
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
                if (value.deprecated) {
                    acc[key] = value;
                }

                return acc;
            },
            {},
        );
    }

    /**
     * Returns the deprecated props which are in the usedProps
     *
     * @param {Object} usedProps
     * @param {Object} deprecatedProps
     * @returns {{}}
     */
    getUsedProps(usedProps, deprecatedProps) {
        return Object.entries(deprecatedProps).reduce(
            (
                acc,
                [
                    propKey,
                    prop,
                ],
            ) => {
                // The deprecated property exists in the current instance props
                if (usedProps.hasOwnProperty(propKey)) {
                    // If the deprecated property has a default?
                    // Then it will also be in the current props with the default value
                    if (prop.hasOwnProperty('default')) {
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

                    acc[propKey] = prop;
                }

                return acc;
            },
            {},
        );
    }

    /**
     * Guard each prop which is deprecated and supplied from another component
     *
     * @param {Component} component
     * @param {Object} deprecationProps
     */
    throwPropsDeprecationErrors(component, deprecationProps) {
        const componentTrace = this.getComponentTrace(component);
        const componentName = component.$options.name;

        Object.entries(deprecationProps).forEach(
            ([
                propName,
                deprecationValue,
            ]) => {
                const deprecationVersion =
                    typeof deprecationValue === 'string' ? deprecationValue : deprecationValue.version;

                let message = `The component "${componentName}" was used with the deprecated property "${propName}".`;
                message += ` The property will be removed in Shopware ${deprecationVersion} \n`;

                if (deprecationValue.comment) {
                    message += `\n ${deprecationValue.comment}`;
                }

                this.guard(deprecationVersion, message + componentTrace);
            },
        );
    }

    /**
     * Guard the component itself with the given deprecationInformation
     *
     * @param {Component} component
     * @param {Object} deprecationInformation
     */
    throwComponentDeprecationInformationErrors(component, deprecationInformation) {
        if (!deprecationInformation) {
            return;
        }

        const { version, comment } = deprecationInformation;
        const componentName = component.$options.name;
        const message = `The component "${componentName}" is deprecated and will be removed in Shopware ${version} \n`;

        this.guard(version, message + comment + this.getComponentTrace(component));
    }

    /**
     * The trace is part of the message so that the same deprecation reported from two different usage
     * sites is warned about twice, while a re-render of one site stays quiet.
     *
     * @param {String} version
     * @param {String} message
     */
    guard(version, message) {
        Shopware.Feature.triggerDeprecationOrThrow(DeprecationPlugin.toMajorFlag(version), message);
    }

    /**
     * Turns a deprecation version into the feature flag that activates its major, so that annotations
     * can keep using the version notation developers already write (`6.8.0`, `v6.8.0.0`).
     *
     * @param {String} version
     * @returns {String}
     */
    static toMajorFlag(version) {
        const segments = String(version).replace(/^v/i, '').split('.');

        while (segments.length < 4) {
            segments.push('0');
        }

        return `V${segments.slice(0, 4).join('_')}`;
    }

    /**
     * Creates a component trace string
     *
     * @param component
     * @returns {String}
     */
    getComponentTrace(component) {
        const trace = [];

        let actualComponent = component;

        while (actualComponent.$parent) {
            trace.push(actualComponent.$options.name);

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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default new DeprecationPlugin();
