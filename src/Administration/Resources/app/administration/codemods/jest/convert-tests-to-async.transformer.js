export default (fileInfo, api) => {
    const j = api.jscodeshift;

    return j(fileInfo.source)
        .find(j.CallExpression, {
            callee: {
                name: 'it'
            }
        })
        .forEach(p => {
            const secondArgument = p.node.arguments[1];

            if (secondArgument.type === 'ArrowFunctionExpression') {
                secondArgument.async = true;
            }

            return p;
        })
        .toSource();
};
