const {
    extractComponentDeclaration,
    extractComputed,
    extractImports,
    extractInject,
    extractLifecycleHooks,
    extractMethods,
    extractMixins,
    extractProps,
    extractWatcher,
    parseSource,
    extractBlockComment
} = require('./source-components');

const lessVariableParser = require('./less-components');
const twigParser = require('./twig-components');

module.exports = (file, globalLessVariables) => {
    const ast = parseSource(file.source);

    const comment = extractBlockComment(ast);
    const imports  = extractImports(ast);
    const componentDeclaration = extractComponentDeclaration(ast);

    if (!componentDeclaration.name || !componentDeclaration.definition) {
        return {};
    }

    const definition = componentDeclaration.definition;
    const props = extractProps(definition);
    const computed = extractComputed(definition);

    const methods = extractMethods(definition);
    const watcher = extractWatcher(definition);

    const hooks = extractLifecycleHooks(definition);
    const mixins = extractMixins(definition);
    const inject = extractInject(definition);

    const lessVariables = lessVariableParser(file, imports, globalLessVariables);
    const twigInformation = twigParser(file, imports);

    return {
        imports,
        props,
        computed,
        methods,
        watcher,
        mixins,
        inject,
        lessVariables,
        hooks,
        meta: comment,
        slots: twigInformation.slots,
        blocks: twigInformation.blocks,
        name: componentDeclaration.name
    };
};
