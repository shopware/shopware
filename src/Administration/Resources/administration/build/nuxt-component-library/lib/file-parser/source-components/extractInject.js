module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedInjects = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'inject') {
            if (item.value.type === 'ObjectExpression') {
                accumulator = item.value.properties;
                return accumulator;
            }

            accumulator = item.value.elements;
        }

        return accumulator;
    }, []);

    return definedInjects.map((item) => {
        return item.value;
    });
};
