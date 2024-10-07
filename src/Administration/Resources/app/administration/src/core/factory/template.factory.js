/**
 * @package admin
 */

// eslint-disable
import Twig from 'twig';
import { cloneDeep } from 'src/core/service/utils/object.utils';

/**
 * @module core/factory/async-template
 * @private
 */
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
    getTemplateOverrides,
};

/**
 * Internal Twig.Templates method
 * @param {null}
 */
let TwigTemplates = null;

/**
 * Extends the Twig core for compatibility.
 */
Twig.extend((TwigCore) => {
    /**
     * Remove tokens output_whitespace_pre, output_whitespace_post, output_whitespace_both and output.
     * These tokens are used for functions and data output.
     * Since the data binding is done in Vue this could lead to syntax issues.
     * We are only using the block system for template inheritance.
     *
     * @type {Array<any>}
     */
    TwigCore.token.definitions = TwigCore.token.definitions.filter((token) => {
        return (
            token.type !== TwigCore.token.type.output_whitespace_pre &&
            token.type !== TwigCore.token.type.output_whitespace_post &&
            token.type !== TwigCore.token.type.output_whitespace_both &&
            token.type !== TwigCore.token.type.output
        );
    });

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
                output: '{{|PARENT|}}',
            };
        },
    });

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
const parentPlaceholder = '{{\\|PARENT\\|}}';

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
 * @private
 * @param {string} componentName
 * @param {string} componentTemplate
 * @returns {boolean}
 */
function registerComponentTemplate(componentName, componentTemplate = null) {
    const template = templateRegistry.get(componentName) || {};
    const overrides = template.overrides ? template.overrides : [];

    templateRegistry.set(componentName, {
        name: componentName,
        raw: componentTemplate,
        extend: null,
        overrides: overrides,
    });

    return true;
}
/**
 * Registers the main template for the component
 * based on the template of the extended component.
 * If the component comes with an own template extension
 * it will also be registered as an override of the extended template.
 *
 * @private
 * @param {String} componentName
 * @param {String} extendComponentName
 * @param {String|null} [templateExtension=null]
 */
function extendComponentTemplate(componentName, extendComponentName, templateExtension = null) {
    const template = templateRegistry.get(componentName) || {};
    const overrides = template.overrides ? template.overrides : [];

    // If a component doesn't override the template, provide an empty string
    if (!templateExtension) {
        templateExtension = '';
    }

    templateRegistry.set(componentName, {
        name: componentName,
        raw: templateExtension,
        extend: extendComponentName,
        overrides: overrides,
    });

    return true;
}

/**
 * Registers an override of a component template.
 * The override can be registered before the main template is defined.
 *
 * @private
 * @param {String} componentName
 * @param {String|null} [templateOverride=null]
 * @param {Number|null} [overrideIndex=0]
 */
function registerTemplateOverride(componentName, templateOverride = null, overrideIndex = 0) {
    const component = templateRegistry.get(componentName) || {
        name: componentName,
        raw: null,
        extend: null,
        overrides: [],
    };
    component.overrides.push({
        index: overrideIndex,
        raw: templateOverride,
    });
    component.overrides.sort((a, b) => a.index - b.index);
    templateRegistry.set(componentName, component);
    return true;
}

function registerNormalizedTemplate(item) {
    let templateDefinition = resolveExtendsComponent(item);

    // extended component was not found
    if (!templateDefinition) {
        normalizedTemplateRegistry.delete(item.name);
        return;
    }

    templateDefinition = {
        ...templateDefinition,
        html: '',
    };

    const hasOverridesInExtensionChain = (component) => {
        if (!component.extend) {
            return false;
        }

        return component.extend.overrides.length > 0 || hasOverridesInExtensionChain(component.extend);
    };
    if (hasOverridesInExtensionChain(templateDefinition)) {
        // If this component extends (transitively) a component that is overwritten, resolve that extended component
        // with all its overrides first, before resolving this component with it.
        registerNormalizedTemplate(templateRegistry.get(templateDefinition.extend.name));
    }

    // Extend with overrides
    const resolvedtokens = resolveExtendTokens(templateDefinition.template.tokens, templateDefinition);
    templateDefinition.template.tokens = resolvedtokens;

    // Write back built template to the registry
    normalizedTemplateRegistry.set(templateDefinition.name, templateDefinition);

    // Apply overrides
    templateDefinition = applyTemplateOverrides(templateDefinition.name);
    templateDefinition.html = templateDefinition.html.replace(parentRegExp, '');

    // Final template will be written to the registry
    normalizedTemplateRegistry.set(templateDefinition.name, templateDefinition);
}

/**
 * Resolves the templates, builds the extend chain, applies overrides, replaces all remaining parent placeholders
 * and updates the item in the registry.
 *
 * @private
 * @returns {Map<String, Object>}
 */
function resolveTemplates() {
    const componentTemplates = Array.from(templateRegistry.values());
    componentTemplates.forEach(registerNormalizedTemplate);

    return normalizedTemplateRegistry;
}

function applyTemplateOverrides(name) {
    const item = normalizedTemplateRegistry.get(name);
    const templateVars = {};

    if (!item.overrides.length) {
        // Render the final rendered output with all overridden blocks
        const finalHtml = item.template.render(templateVars);

        // Update item which will be written to the registry
        const updatedTemplate = {
            ...item,
            html: finalHtml,
        };

        normalizedTemplateRegistry.set(updatedTemplate.name, updatedTemplate);
        return updatedTemplate;
    }

    const baseTemplate = normalizedTemplateRegistry.get(item.name);

    // iterate the overrides per component
    item.overrides.forEach((override, index) => {
        const overrideTemplate = buildTwigTemplateInstance(`${baseTemplate.name}-${index}`, override.raw);

        overrideTemplate.tokens.forEach((overrideTokens) => {
            // resolve the template tokens
            if (overrideTokens.type === 'logic') {
                baseTemplate.template.tokens = resolveTokens(baseTemplate.template.tokens, [overrideTokens], name);
            }
        });
    });

    normalizedTemplateRegistry.set(baseTemplate.name, baseTemplate);

    let updatedTemplate = normalizedTemplateRegistry.get(item.name);

    // Render the final rendered output with all overridden blocks
    const finalHtml = updatedTemplate.template.render(templateVars);

    // Update item which will written to the registry
    updatedTemplate = {
        ...updatedTemplate,
        html: finalHtml,
    };

    normalizedTemplateRegistry.set(updatedTemplate.name, updatedTemplate);

    return updatedTemplate;
}

/**
 * Resolve template tokens
 * @param {Object[]} tokens
 * @param {Object[]} overrideTokens
 * @returns {Object} | undefined
 */
function resolveTokens(tokens, overrideTokens) {
    if (!tokens) {
        return [];
    }

    return tokens.reduce((acc, token) => {
        if (token.type !== 'logic' || !token.token || !token.token.blockName) {
            return [
                ...acc,
                token,
            ];
        }

        const blockName = token.token.blockName;
        const isInOverrides = findBlock(blockName, overrideTokens);

        if (isInOverrides) {
            if (isInOverrides.type === 'logic') {
                isInOverrides.token.output = mergeTokens(token, isInOverrides.token.output);
            }

            return [
                ...acc,
                isInOverrides,
            ];
        }

        const resolvedTokens = resolveTokens(token.token.output, overrideTokens);

        token.token.output = resolvedTokens;

        return [
            ...acc,
            token,
        ];
    }, []);
}

function mergeTokens(token, tokens) {
    return tokens.reduce((acc, t) => {
        if (t.type === 'logic' && t.token.type === 'parent') {
            return [
                ...acc,
                ...token.token.output,
            ];
        }

        if (t.token?.output) {
            t.token.output = resolveSubTokens(t.token.output, token.token.output);
        }

        return [
            ...acc,
            t,
        ];
    }, []);
}

/**
 * Resolve parent in sub-token
 * @param {Object[]} subToken
 * @param {Object} replacement
 * @returns {Object[]}
 */
function resolveSubTokens(subToken, replacement) {
    return subToken.reduce((xs, s) => {
        if (s.type === 'logic' && s.token.type === 'parent') {
            return [
                ...xs,
                ...replacement,
            ];
        }

        return [
            ...xs,
            s,
        ];
    }, []);
}

/**
 * Resolve token of the extension
 * @param {Object[]} tokens
 * @param {Object} item
 * @returns {Object[]}
 */
function resolveExtendTokens(tokens, item) {
    if (!item.extend) {
        return tokens;
    }

    let extendedComponentTokens;
    if (normalizedTemplateRegistry.has(item.extend.name)) {
        // If the component was already registered in the normalizedTemplateRegistry (i.e. their overrides and tokens
        // have been resolved), use that template's tokens instead of the raw tokens of an unresolved component.
        // Use a clone of the tokens so the already registered template is not altered.
        extendedComponentTokens = cloneDeep(normalizedTemplateRegistry.get(item.extend.name).template.tokens);
    } else {
        extendedComponentTokens = item.extend.template.tokens;
    }

    const extensionTokens = Array.from(resolveExtendTokens(extendedComponentTokens, item.extend));
    const itemTokens = normalizeTokens(Array.from(tokens), extensionTokens);

    tokens = extensionTokens.map((token) => {
        return resolveToken(token, itemTokens, item.name);
    });

    return tokens;
}

/**
 * Normalize itemTokens - remove wrapping block which doesn't exist in the extension token tree
 * @param {Object[]} tokens
 * @param {Object[]} extensionTokens
 * @returns {Object[]}
 */
function normalizeTokens(tokens, extensionTokens) {
    const result = tokens.reduce((acc, token) => {
        if (token.token && !findNestedBlock(token.token.blockName, extensionTokens)) {
            return [
                ...acc,
                ...token.token.output,
            ];
        }

        return [
            ...acc,
            token,
        ];
    }, []);

    return result;
}

/**
 * Search deeply for a token by name in a list of tokens and their tokens
 * @param {String} blockName
 * @param {Object[]} tokens
 * @returns {Object} | undefined
 */
function findNestedBlock(blockName, tokens) {
    const result = tokens.find((t) => {
        const exists = t.token && t.token.blockName === blockName;

        return exists || (t.token && findNestedBlock(blockName, t.token.output));
    });

    return result;
}

/**
 * Search for a token by name in a list of tokens
 * @param {String} blockName
 * @param {Object[]} tokens
 * @returns {Object} | undefined
 */
function findBlock(blockName, tokens) {
    const result = tokens.find((t) => {
        return t.token && t.token.blockName === blockName;
    });

    return result;
}

function resolveToken(token, itemTokens, name) {
    // plain html - just return the token
    if (token.type !== 'logic') {
        return token;
    }

    // Vue 3 - if/else token support
    const ifElseTokenTypes = [
        'Twig.logic.type.if',
        'Twig.logic.type.else',
        'Twig.logic.type.endif',
    ];
    if (token.type === 'logic' && ifElseTokenTypes.includes(token.token.type)) {
        return token;
    }

    const tokenBlockName = token.token.blockName;
    const isIn = findBlock(tokenBlockName, itemTokens);

    if (isIn) {
        if (isIn.type !== 'logic') {
            return isIn;
        }

        isIn.token.output = mergeTokens(token, isIn.token.output);

        return isIn;
    }

    // resolve the outputs in depth
    token.token.output = token.token.output.map((t) => {
        return resolveToken(t, itemTokens, name);
    });

    return token;
}

/**
 * Resolves the extend chain for a given component
 * @param {Object} item
 * @returns {null|Object}
 */
function resolveExtendsComponent(item) {
    if (!item) {
        return null;
    }

    if (item.extend) {
        const extend = resolveExtendsComponent(templateRegistry.get(item.extend));
        if (!extend) {
            return null;
        }

        return {
            ...item,
            template: buildTwigTemplateInstance(item.name, item.raw),
            extend,
        };
    }

    return {
        ...item,
        template: buildTwigTemplateInstance(item.name, item.raw),
    };
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
        options: {},
    });
}

/**
 * Clears the twig cache
 * @private
 * @returns {void}
 */
function clearTwigCache() {
    Twig.clearRegistry();
}

/**
 * Returns the twig cache
 *
 * @private
 * @returns {Object}
 */
function getTwigCache() {
    return Twig.getRegistry();
}

/**
 * Disables the twig cache
 *
 * @private
 * @returns {void}
 */
function disableTwigCache() {
    Twig.cache(false);
}

/**
 * Get the complete template registry.
 *
 * @private
 * @returns {Map}
 */
function getTemplateRegistry() {
    return templateRegistry;
}

/**
 * Get the complete template registry which got normalized including
 * twig templates and the pre-rendered markup
 *
 * @private
 * @returns {Map}
 */
function getNormalizedTemplateRegistry() {
    return normalizedTemplateRegistry;
}

/**
 * Get all template overrides which are registered for a component.
 *
 * @private
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
 * @private
 * @param componentName
 * @returns {null|string}
 */
function getRenderedTemplate(componentName) {
    const component = templateRegistry.get(componentName);

    if (!component) {
        return null;
    }

    registerNormalizedTemplate(component);

    const componentTemplate = normalizedTemplateRegistry.get(componentName);

    if (!componentTemplate) {
        return null;
    }

    return componentTemplate.html;
}
