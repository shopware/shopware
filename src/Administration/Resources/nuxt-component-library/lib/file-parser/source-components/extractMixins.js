module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definedMixins = moduleDefinition.reduce((accumulator, item) => {
        if (item.key.name === 'mixins') {
            accumulator = item.value.elements;
        }

        return accumulator;
    }, []);

    return definedMixins.map((item) => {
        return item.arguments.reduce((accumulator, arg) => {
            if (arg.type === 'Literal') {
                accumulator = arg.value;
            }
            return accumulator;
        }, null);
    });
};