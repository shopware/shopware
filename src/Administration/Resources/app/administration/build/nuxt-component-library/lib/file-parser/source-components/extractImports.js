module.exports = (ast) => {
    if (!ast.body) {
        return [];
    }

    return ast.body.reduce((accumulator, declaration) => {
        if (declaration.type === 'ImportDeclaration') {
            accumulator.push(declaration.source.value);
        }
        return accumulator;
    }, []);
};