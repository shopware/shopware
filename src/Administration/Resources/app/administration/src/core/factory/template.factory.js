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
    resolveTemplates,
    clearTwigCache,
    getTwigCache,
    disableTwigCache,
    getTemplateRegistry,
    getNormalizedTemplateRegistry,
    getTemplateOverrides
};

/**
 * Internal Twig.Templates method
 * @param {null}
 */
let TwigTemplates = null;

/**
 * Extends the Twig core for compatibility.
 */
Twig.extend(TwigCore => {
    /**
     * Remove tokens 2 (output_whitespace_pre), 3 (output_whitespace_post), 4 (output_whitespace_both) and 8 (output).
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

    TwigTemplates = TwigCore.Templates;
    TwigCore.cache = false;
});

/**
 * Escaped parent placeholder
 * @type {string}
 */
const parentPlaceholder = Twig.placeholders.parent.replace(/\|/g, '\\|');

/**
 * Parent placeholder as regular expression
 * @type {RegExp}
 */
const parentRegExp = new RegExp(parentPlaceholder, 'gm');

/**
 * Holds a list with all registered component templates.
 * Including registered overrides.
 *
 * @type {Map<String, Object>}
 */
const templateRegistry = new Map();

/**
 * Holds a list with all registered normalized component templates.
 * Including registered overrides.
 *
 * Each template contains the following information
 * - name - name of the component associated with the template
 * - template - Twig.Template which represents the template raw
 * - raw - Raw template string
 * - html - Pre-rendered markup
 * - extends - If a component extends another component, it will be listed here
 *
 * @type {Map<String, Object>}
 */
const normalizedTemplateRegistry = new Map();

/**
 * Registers the main template for the defined component.
 *
 * @param componentName
 * @param componentTemplate
 * @returns {boolean}
 */
function registerComponentTemplate(componentName, componentTemplate = null) {
    const template = templateRegistry.get(componentName) || {};
    const overrides = (template.overrides ? template.overrides : []);

    templateRegistry.set(componentName, {
        name: componentName,
        raw: componentTemplate,
        extend: null,
        overrides: overrides
    });

    return true;
}
/**
 * Registers the main template for the component
 * based on the template of the extended component.
 * If the component comes with an own template extension
 * it will also be registered as an override of the extended template.
 *
 * @param {String} componentName
 * @param {String} extendComponentName
 * @param {String|null} [templateExtension=null]
 */
function extendComponentTemplate(
    componentName,
    extendComponentName,
    templateExtension = null
) {
    if (templateRegistry.has(componentName)) {
        warn('TemplateRegistry', '"componentName" is registered already');
        return false;
    }

    // If a component doesn't override the template, provide an empty string
    if (!templateExtension) {
        templateExtension = '';
    }

    templateRegistry.set(componentName, {
        name: componentName,
        raw: templateExtension,
        extend: extendComponentName,
        overrides: []
    });

    return true;
}

/**
 * Registers an override of a component template.
 * The override can be registered before the main template is defined.
 *
 * @param {String} componentName
 * @param {String|null} [templateOverride=null]
 * @param {Number} [overrideIndex=0]
 */
function registerTemplateOverride(
    componentName,
    templateOverride = null,
    overrideIndex = 0
) {
    const component = templateRegistry.get(componentName) || {
        name: componentName,
        raw: null,
        extend: null,
        overrides: []
    };
    component.overrides.push({
        index: overrideIndex,
        raw: templateOverride
    });
    templateRegistry.set(componentName, component);
    return true;
}

/**
 * Resolves the templates, builds the extend chain, applies overrides, replaces all remaining parent placeholders
 * and updates the item in the registry.
 *
 * @returns {Map<String, Object>}
 */
function resolveTemplates() {
    const componentTemplates = Array.from(templateRegistry.values());

    componentTemplates.forEach(item => {
        let templateDefinition = resolveExtendsComponent(item);

        templateDefinition = {
            ...templateDefinition,
            html: renderExtendedTemplate(templateDefinition)
        };
        // Write back built template to the registry
        normalizedTemplateRegistry.set(templateDefinition.name, templateDefinition);

        // Apply overrides
        templateDefinition = applyTemplateOverrides(templateDefinition.name);
        templateDefinition.html = templateDefinition.html.replace(parentRegExp, '');

        // Final template will be written to the registry
        normalizedTemplateRegistry.set(templateDefinition.name, templateDefinition);
    });

    return normalizedTemplateRegistry;
}

function applyTemplateOverrides(name) {
    const item = normalizedTemplateRegistry.get(name);

    if (!item.overrides.length) {
        return item;
    }

    // Iterate the overrides per component
    let overriddenBlocks = {};
    item.overrides.forEach(override => {
        const baseTemplate = normalizedTemplateRegistry.get(item.name);
        const overrideTemplate = buildTwigTemplateInstance(
            `${baseTemplate.name}-${override.index}`,
            override.raw
        );

        // Get the rendered blocks
        const renderedOverride = overrideTemplate.render({}, {
            output: 'blocks'
        });

        // Merge the blocks with the previous blocks
        Object.keys(baseTemplate.template.blocks).forEach(blockName => {
            if (renderedOverride[blockName]) {
                renderedOverride[blockName] = renderedOverride[blockName].replace(
                    parentRegExp,
                    baseTemplate.template.blocks[blockName]
                );
            }
        });

        // Apply blocks and render the output html
        overriddenBlocks = { ...baseTemplate.template.blocks, ...renderedOverride };
        baseTemplate.template.blocks = overriddenBlocks;

        normalizedTemplateRegistry.set(baseTemplate.name, baseTemplate);
    });

    let updatedTemplate = normalizedTemplateRegistry.get(item.name);

    // Render the final rendered output with all overridden blocks
    const finalHtml = updatedTemplate.template.render({}, {
        blocks: overriddenBlocks
    });

    // Update item which will written to the registry
    updatedTemplate = {
        ...updatedTemplate,
        html: finalHtml
    };

    normalizedTemplateRegistry.set(updatedTemplate.name, updatedTemplate);
    return updatedTemplate;
}

/**
 * Renders recursively the component templates. The rendering will be skipped when an override was found. The
 * rendering will be skipped
 *
 * @param {Object} item
 * @param {Object|null} [parentTemplate=null]
 * @returns {null|String} Either `null` or the rendered html output
 */
function renderExtendedTemplate(item, parentTemplate = null) {
    const params = {};

    // When we're having overrides, we skip the template rendering
    if ((item.overrides && item.overrides.length) && parentTemplate === null) {
        return null;
    }

    if (parentTemplate) {
        params.blocks = parentTemplate.blocks;
    }

    const renderedTemplate = item.template.render({}, params);

    if (!item.extend) {
        return renderedTemplate;
    }

    return renderExtendedTemplate(item.extend, item.template);
}

/**
 * Resolves the extend chain for a given component
 * @param {Object} item
 * @returns {Object}
 */
function resolveExtendsComponent(item) {
    item = { ...item, template: buildTwigTemplateInstance(item.name, item.raw) };

    if (item.extend) {
        item = {
            ...item,
            extend: resolveExtendsComponent(templateRegistry.get(item.extend))
        };
    }

    return item;
}

/**
 * Creates a new Twig.Template instance which will be later used to render the component template
 * @param {String} name - component name
 * @param {String} template - Template raw string
 * @returns {Twig.Template}
 */
function buildTwigTemplateInstance(name, template) {
    return TwigTemplates.parsers.twig({
        id: `${name}-baseTemplate`,
        data: template,
        path: false,
        options: {}
    });
}

/**
 * Clears the twig cache
 * @returns {void}
 */
function clearTwigCache() {
    Twig.clearRegistry();
}

/**
 * Returns the twig cache
 *
 * @returns {Object}
 */
function getTwigCache() {
    return Twig.getRegistry();
}

/**
 * Disables the twig cache
 *
 * @returns {void}
 */
function disableTwigCache() {
    Twig.cache(false);
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
 * Get the complete template registry which got normalized including
 * twig templates and the pre-rendered markup
 *
 * @returns {Map}
 */
function getNormalizedTemplateRegistry() {
    return normalizedTemplateRegistry;
}

/**
 * Get all template overrides which are registered for a component.
 *
 * @param componentName
 * @returns {Array}
 */
function getTemplateOverrides(componentName) {
    if (!templateRegistry.has(componentName)) {
        return [];
    }

    const template = templateRegistry.get(componentName);

    return template.overrides || [];
}

/**
 * Returns the rendered markup for the component template including all template overrides.
 *
 * @param componentName
 * @returns {string}
 */
function getRenderedTemplate(componentName) {
    if (!normalizedTemplateRegistry.has(componentName)) {
        return '';
    }

    const componentTemplate = normalizedTemplateRegistry.get(componentName);
    return componentTemplate.html;
}
