import Twig from 'twig';

export default {
    registerComponentTemplate,
    extendComponentTemplate,
    registerTemplateOverride,
    getRenderedTemplate,
    getTemplateOverrides,
    getTemplateRegistry,
    findCustomTemplate,
    findCustomOverride
};

/**
 * Holds a list with all registered component templates.
 * Including registered overrides.
 *
 * @type {Map}
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
     * @type {[*]}
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

    /**
     * Make the placeholders available in the exposed Twig object.
     */
    TwigCore.exports.placeholders = TwigCore.placeholders;
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

    template.baseTemplate = Twig.twig(templateConfig);

    templateRegistry.set(componentName, template);
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
    if (!templateRegistry.has(extendComponentName)) {
        if (templateExtension !== null) {
            registerComponentTemplate(componentName, templateExtension);
        }

        return;
    }

    const extendTemplate = templateRegistry.get(extendComponentName);
    const template = templateRegistry.get(componentName) || {};

    const templateConfig = {
        id: `${componentName}-baseTemplate`,
        data: extendTemplate.baseTemplate.tokens
    };

    template.baseTemplate = Twig.twig(templateConfig);

    templateRegistry.set(componentName, template);

    if (templateExtension !== null) {
        registerTemplateOverride(componentName, templateExtension);
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
        return '';
    }

    /**
     * The base template is the main template of the component.
     */
    const baseTemplate = template.baseTemplate;
    const overrides = template.overrides;
    const parentPlaceholder = Twig.placeholders.parent;
    const blocks = {};

    baseTemplate.render();

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
