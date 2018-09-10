module.exports = (ast) => {
    if (!ast.body) {
        return {};
    }

    const definition = ast.body.reduce((accumulator, declaration) => {
        if (declaration.type === 'ExpressionStatement') {
            accumulator = declaration;
        }
        return accumulator;
    }, null);

    if (!definition || !definition.expression || !definition.expression.arguments) {
        return {};
    }

    const args = definition.expression.arguments;

    const moduleName = args.reduce((accumulator, declaration) => {
        if (declaration.type === 'Literal') {
            accumulator = declaration.value;
        }

        return accumulator;
    }, null);

    const moduleDefinition = args.reduce((accumulator, declaration) => {
        if (declaration.type === 'ObjectExpression') {
            accumulator = declaration.properties;
        }

        return accumulator;
    }, []);

    return {
        name: moduleName,
        definition: moduleDefinition
    };
}