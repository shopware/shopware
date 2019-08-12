/**
 * @module core/factory/template
 */
import Twig from 'twig';
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    registerComponentTemplate,
    extendComponentTemplate,
    registerTemplateOverride,
    getRenderedTemplate,
    getTemplateOverrides,
    getTemplateRegistry,
    findCustomTemplate,
    findCustomOverride,
    clearTwigCache,
    getTwigCache,
    disableTwigCache
};

/**
 * Holds a list with all registered component templates.
 * Including registered overrides.
 *
 * @type {Map<any, any>}
 */
const templateRegistry = new Map();

/**
 * Extends the Twig core for compatibility.
 */
Twig.extend((TwigCore) => {
    /**
     * Remove tokens 2, 3, 4 and 8.
     * This tokens are used for functions and data output.
     * Since the data binding is done in Vue this could lead to syntax issues.
     * We are only using the block system for template inheritance.
     *
     * @type {Array<any>}
     */
    TwigCore.token.definitions = [
        TwigCore.token.definitions[0],
        TwigCore.token.definitions[1],
        TwigCore.token.definitions[5],
        TwigCore.token.definitions[6],
        TwigCore.token.definitions[7],
        TwigCore.token.definitions[9],
        TwigCore.token.definitions[10]
    ];

    /**
     * Twig inheritance extension.
     * The parent function is used as a statement tag.
     * This is used to prevent syntax issues between Twig and Vue.
     * Use `{% parent %}` to print out the parent content of a block.
     */
    TwigCore.exports.extendTag({
        type: 'parent',
        regex: /^parent/,
        next: [],
        open: true,

        parse(token, context, chain) {
            return {
                chain,
                output: TwigCore.placeholders.parent
            };
        }
    });

    /** Make the placeholders available in the exposed Twig object. */
    TwigCore.exports.placeholders = TwigCore.placeholders;

    /** Make the Twig template cache registry available. */
    TwigCore.exports.getRegistry = function getRegistry() {
        return TwigCore.Templates.registry;
    };

    /** Provide possibility to clear the template cache registry */
    TwigCore.exports.clearRegistry = function clearRegistry() {
        TwigCore.Templates.registry = {};
    };
});

/**
 * Registers the main template for the defined component.
 *
 * @param componentName
 * @param componentTemplate
 */
function registerComponentTemplate(componentName, componentTemplate = null) {
    const template = templateRegistry.get(componentName) || {};

    /**
     * If there is no template given, search the DOM.
     */
    if (componentTemplate === null) {
        componentTemplate = findCustomTemplate(componentName);
    }

    const templateConfig = {
        id: `${componentName}-baseTemplate`,
        data: componentTemplate
    };

    try {
        template.baseTemplate = Twig.twig(templateConfig);
    } catch (error) {
        warn(error.message);
        return false;
    }

    templateRegistry.set(componentName, template);
    return true;
}

/**
 * Registers the main template for the component
 * based on the template of the extended component.
 * If the component comes with an own template extension
 * it will also be registered as an override of the extended template.
 *
 * @param componentName
 * @param extendComponentName
 * @param templateExtension
 */
function extendComponentTemplate(componentName, extendComponentName, templateExtension = null) {
    const templateConfig = {
        extendsFrom: extendComponentName,
        baseTemplate: null,
        overrides: []
    };
    templateRegistry.set(componentName, templateConfig);

    if (templateExtension !== null) {
        registerTemplateOverride(componentName, templateExtension, 0);
    }
}

/**
 * Registers an override of a component template.
 * The override can be registered before the main template is defined.
 *
 * @param componentName
 * @param templateOverride
 * @param overrideIndex
 */
function registerTemplateOverride(componentName, templateOverride = null, overrideIndex = null) {
    const template = templateRegistry.get(componentName) || {};

    template.overrides = template.overrides || [];

    if (templateOverride === null) {
        templateOverride = findCustomOverride(componentName);
    }

    const templateConfig = {
        id: `${componentName}-${template.overrides.length}`,
        data: templateOverride
    };

    const override = Twig.twig(templateConfig);

    /**
     * You can change the inheritance order by defining the override index.
     */
    if (overrideIndex !== null) {
        template.overrides.splice(overrideIndex, 0, override);
    } else {
        template.overrides.push(override);
    }

    templateRegistry.set(componentName, template);
}

/**
 * Returns the rendered markup for the component template including all template overrides.
 *
 * @param componentName
 * @returns {string}
 */
function getRenderedTemplate(componentName) {
    if (!templateRegistry.has(componentName)) {
        return '';
    }

    const template = templateRegistry.get(componentName);

    if (!template.baseTemplate) {
        // build baseTemplate form parent component
        if (template.extendsFrom) {
            let templateConfig = {};

            if (hasBlocks(template.extendsFrom)) {
                // use baseTemplate from parent component
                const extendTemplate = templateRegistry.get(template.extendsFrom);

                templateConfig = {
                    id: `${componentName}-baseTemplate`,
                    data: extendTemplate.baseTemplate.tokens
                };

                template.baseTemplate = Twig.twig(templateConfig);
            } else if (template.overrides.length > 0) {
                // use first override as baseTemplate
                // and remove it from overrides
                const firstOverride = template.overrides.shift();

                template.baseTemplate = firstOverride;
            } else {
                warn(componentName, 'has no overrides or template to extend from!');
                return '';
            }
        } else {
            warn('missing baseTemplate', componentName);
            return '';
        }
    }

    /**
     * The base template is the main template of the component.
     */
    const { baseTemplate, overrides } = template;
    const parentPlaceholder = Twig.placeholders.parent;
    const blocks = {};

    /**
     * Iterate through template extensions and collect all block overrides.
     */
    if (overrides) {
        overrides.forEach((override) => {
            const templateBlocks = override.render({}, {
                output: 'blocks'
            });

            /**
             * Replace the parent placeholder with the parent block.
             * This ensures multi level inheritance.
             */
            Object.keys(blocks).forEach((blockName) => {
                if (templateBlocks[blockName]) {
                    templateBlocks[blockName] = templateBlocks[blockName].replace(
                        parentPlaceholder,
                        blocks[blockName]
                    );
                }
            });

            Object.assign(blocks, templateBlocks);
        });
    }

    /**
     * Render the base template with all collected block overrides.
     */
    return baseTemplate.render({}, {
        blocks
    });
}

/**
 * Get all template overrides which are registered for a component.
 *
 * @param componentName
 * @returns {*}
 */
function getTemplateOverrides(componentName) {
    if (!templateRegistry.has(componentName)) {
        return [];
    }

    const template = templateRegistry.get(componentName);

    return template.overrides || [];
}

/**
 * Get the complete template registry.
 *
 * @returns {Map}
 */
function getTemplateRegistry() {
    return templateRegistry;
}

/**
 * Returns "true" if components baseTemplate contains twig blocks.
 *
 * @param componentName
 * @returns {Boolean}
 */
function hasBlocks(componentName) {
    const { baseTemplate } = templateRegistry.get(componentName);

    if (baseTemplate === null) {
        return false;
    }

    const templateBlocks = baseTemplate.render({}, {
        output: 'blocks'
    });

    return !isEmptyObject(templateBlocks);
}

/**
 * Returns "true" if an object has no properties,
 * otherwise "false".
 *
 * @param {Object} object
 * @returns {Boolean}
 */
function isEmptyObject(object) {
    return Object.entries(object).length === 0
        && object.constructor === Object;
}

/**
 * Find a component template in the DOM.
 * You can define component templates by creating a template element with a `component` attribute.
 * The attribute should contain the name of the component.
 *
 * @param componentName
 * @returns {String}
 */
function findCustomTemplate(componentName) {
    const element = document.querySelector(`template[component="${componentName}"]`);

    return (element !== null) ? element.innerHTML : '';
}

/**
 * Find a template override in the DOM.
 * You can define template overrides by creating a template element with a `override` attribute.
 * The attribute should contain the name of the component you want to override.
 *
 * @param componentName
 * @returns {String}
 */
function findCustomOverride(componentName) {
    const element = document.querySelector(`template[override="${componentName}"]`);

    return (element !== null) ? element.innerHTML : '';
}

function clearTwigCache() {
    Twig.clearRegistry();
}

function getTwigCache() {
    return Twig.getRegistry();
}

function disableTwigCache() {
    Twig.cache(false);
}
