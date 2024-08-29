import { getCurrentInstance } from 'vue';

const { warn } = Shopware.Utils.debug;

/**
 * @package admin
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
    pluginInstalled = false;

    /**
     * Installs the Vue Plugin
     *
     * @param Vue {Vue}
     * @returns {boolean} is successfully installed
     */
    install(Vue) {
        const _this = this;

        if (this.pluginInstalled) {
            warn('Deprecation Plugin', 'This plugin is already installed');
            return false;
        }

        Vue.mixin({
            created() {
                const instance = getCurrentInstance();
                if (!instance) return;


                const { props } = instance.type;
                const propsData = instance.props;

                const deprecatedProps = _this.getDeprecatedProps(props);

                const usedDeprecationProps = _this.getUsedProps(propsData, deprecatedProps);
                const componentDeprecationInformation = _this.getComponentDeprecationInformation(this);

                _this.throwComponentDeprecationInformationErrors(this, componentDeprecationInformation);
                _this.throwPropsDeprecationErrors(this, usedDeprecationProps);
            },
        });

        this.pluginInstalled = true;

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

        return Object.entries(props).reduce((acc, [key, value]) => {
            if (value.deprecated) {
                acc[key] = value;
            }

            return acc;
        }, {});
    }

    /**
     * Returns the deprecated props which are in the usedProps
     *
     * @param {Object} usedProps
     * @param {Object} deprecatedProps
     * @returns {{}}
     */
    getUsedProps(usedProps, deprecatedProps) {
        return Object.entries(deprecatedProps).reduce((acc, [propKey, prop]) => {
            // The deprecated property exists in the current instance props
            if (usedProps.hasOwnProperty(propKey)) {
                // If the deprecated property has a default? Then it will also be in the current props with the default value
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
        }, {});
    }

    /**
     * Throw an error for each prop which is deprecated and used from another component
     *
     * @param {Component} component
     * @param {Object} deprecationProps
     */
    throwPropsDeprecationErrors(component, deprecationProps) {
        const componentTrace = this.getComponentTrace(component);
        const componentName = component.$options.name;

        Object.entries(deprecationProps).forEach(([propName, deprecationValue]) => {
            const deprecationVersion = typeof deprecationValue === 'string' ? deprecationValue : deprecationValue.version;

            let warningText = `The component "${componentName}" was used with the deprecated property "${propName}".`;
            warningText += ` The property will be removed in Shopware ${deprecationVersion} \n`;

            if (deprecationValue.comment) {
                warningText += `\n ${deprecationValue.comment}`;
            }

            warn(componentName, warningText);
            warn(componentName, componentTrace);
        });
    }

    /**
     * Throw an error with trace with the given deprecationInformation
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
        const warningText = `The component "${componentName}" is deprecated and will be removed in Shopware ${version} \n`;

        warn(componentName, warningText + comment);
        warn(componentName, this.getComponentTrace(component));
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
