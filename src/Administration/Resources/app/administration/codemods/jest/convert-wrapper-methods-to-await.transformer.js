export default (fileInfo, api) => {
    const j = api.jscodeshift;

    return j(fileInfo.source)
        .find(j.ExpressionStatement, p => {
            const isCallExpression = p.expression?.type === 'CallExpression';
            const calleeIsTypeMemberExpression = p.expression?.callee?.type === 'MemberExpression';
            const calleePropertyTypeIsIdentifier = p.expression?.callee?.property?.type === 'Identifier';
            const calleePropertyNameIsIdentifier = [
                'setChecked',
                'setData',
                'setMethods',
                'setProps',
                'setSelected',
                'setValue',
                'trigger'
            ].includes(p.expression?.callee?.property?.name);

            const containFalse = [
                isCallExpression,
                calleeIsTypeMemberExpression,
                calleePropertyTypeIsIdentifier,
                calleePropertyNameIsIdentifier
            ].includes(false);

            return !containFalse;
        })
        .forEach(p => {
            const callExpression = p.node.expression;

            if (callExpression.type === 'CallExpression') {
                p.node.expression = j.awaitExpression(
                    callExpression
                );
            }

            return p;
        })
        .toSource();
};
