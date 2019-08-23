module.exports = (ast) => {
    let args = {};
    if (!ast.body) {
        return args;
    }

    const definition = ast.body.reduce((accumulator, declaration) => {
        if (declaration.type === 'ExpressionStatement' || declaration.type === 'ExportDefaultDeclaration') {
            accumulator = declaration;
        }
        return accumulator;
    }, null);

    if (!definition) {
        return args;
    }

    if (Object.prototype.hasOwnProperty.call(definition, 'expression')) {
        args = definition.expression.arguments;
        return getOldStructureInformation(args);
    } if (Object.prototype.hasOwnProperty.call(definition, 'declaration')) {
        args = definition.declaration.properties;
        return getNewStructureInformation(args);
    }

    return args;
};

function getNewStructureInformation(args) {
    function getModuleName(args) {
        return args.reduce((accumulator, declaration) => {
            if (declaration.key.name === 'name' && declaration.value.type === 'Literal') {
                accumulator = declaration.value.value;
            }
            return accumulator;
        }, null);
    }

    function getExtendedFromName(args) {
        return args.reduce((accumulator, declaration) => {
            if (declaration.key.name === 'extendsFrom' && declaration.value.type === 'Literal') {
                accumulator = declaration.value.value;
            }
            return accumulator;
        }, null);
    }

    return {
        name: getModuleName(args),
        extendsFrom: getExtendedFromName(args),
        definition: args
    };
}

function getOldStructureInformation(args) {
    function getModuleName(args) {
        if (!args.length) {
            return null;
        }
        return args[0].value;
    }

    function getModuleDefinition(args) {
        return args.reduce((accumulator, declaration) => {
            if (declaration.type === 'ObjectExpression' && !accumulator) {
                accumulator = declaration.properties;
            }

            return accumulator;
        }, null) || [];
    }

    function getExtendedFromName(args) {
        if (args.length < 3) {
            return null;
        }

        return args[1].value;
    }

    return {
        name: getModuleName(args),
        definition: getModuleDefinition(args),
        extendsFrom: getExtendedFromName(args)
    };
}
