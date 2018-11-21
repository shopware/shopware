module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedMethods = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'methods') {
            accumulator = item.value.properties;
        }

        return accumulator;
    }, []);
    
    return definedMethods.map((item) => {
        const params = item.value.params || [];
        const definedParams = params.map((param) => {
            if (param.type === 'Identifier') {
                return param.name;
            }
            if (param.type === 'AssignmentPattern') {
                return param.left.name;
            }

            if (param.type === 'RestElement') {
                return `...${param.argument.name}`;
            }
        });

        return {
            name: item.key.name,
            params: definedParams
        };
    });
};
