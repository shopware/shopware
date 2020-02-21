const {
    extractComponentDeclaration,
    extractComputed,
    extractImports,
    extractInject,
    extractLifecycleHooks,
    extractMethods,
    extractMixins,
    extractProps,
    extractDeprecations,
    extractWatcher,
    parseSource,
    extractBlockComment
} = require('./source-components');

const sassVariableParser = require('./sass-components');
const twigParser = require('./twig-components');

function formatReadableName(value) {
    value = value.replace('sw-', '').replace(/(\-\w)/g, (matches) => {
        return ` ${matches[1].toUpperCase()}`;
    });
    value = `${value.charAt(0).toUpperCase()}${value.slice(1)}`;
    return value;
}

module.exports = (file, globalVariables) => {
    const ast = parseSource(file.source);

    const comment = extractBlockComment(ast);
    const imports = extractImports(ast);
    const componentDeclaration = extractComponentDeclaration(ast);

    if (!componentDeclaration.name || !componentDeclaration.definition) {
        return {};
    }

    const definition = componentDeclaration.definition;
    const props = extractProps(definition);
    const computed = extractComputed(definition);

    const deprecations = extractDeprecations(definition);

    const methods = extractMethods(definition);
    const watcher = extractWatcher(definition);

    const hooks = extractLifecycleHooks(definition);
    const mixins = extractMixins(definition);
    const inject = extractInject(definition);

    const sassVariables = sassVariableParser(file, imports, globalVariables);
    const twigInformation = twigParser(file, imports);

    return {
        imports,
        props,
        deprecations,
        computed,
        methods,
        watcher,
        mixins,
        inject,
        sassVariables,
        hooks,
        meta: comment,
        slots: twigInformation.slots,
        blocks: twigInformation.blocks,
        name: componentDeclaration.name,
        extendsFrom: componentDeclaration.extendsFrom,
        readableName: formatReadableName(componentDeclaration.name)
    };
};
