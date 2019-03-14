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
const sassVariableParser = require('./sass-components');
const twigParser = require('./twig-components');

module.exports = (file, globalVariables) => {
    const ast = parseSource(file.source);

    const comment = extractBlockComment(ast);
    const imports = extractImports(ast);
    const componentDeclaration = extractComponentDeclaration(ast);

    if (!componentDeclaration.name || !componentDeclaration.definition) {
        console.log('definition not found');
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

    const lessVariables = lessVariableParser(file, imports, globalVariables);
    const sassVariables = sassVariableParser(file, imports, globalVariables);
    console.log(sassVariables);
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
        sassVariables,
        hooks,
        meta: comment,
        slots: twigInformation.slots,
        blocks: twigInformation.blocks,
        name: componentDeclaration.name
    };
};
