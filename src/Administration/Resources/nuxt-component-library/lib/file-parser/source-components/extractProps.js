function parseProp(definedProp) {
    const key = definedProp.key.name;

    if (!definedProp.value.properties) {
        return
    }

    const prop = definedProp.value.properties.reduce((accumulator, definition) => {
        let value;

        if (definition.value.type === 'Identifier') {
            value = definition.value.name;
        }

        if (definition.value.type === 'Literal') {
            value = definition.value.value;
        }

        if (definition.value.type === 'ObjectExpression') {
            value = {};
        }

        if (definition.value.type === 'ArrayExpression') {
            value = definition.value.elements.map((element) => {
                return { value: element.value, display: element.value };
            });
        }

        accumulator[definition.key.name] = value;
        
        return accumulator;
    }, {});
    return Object.assign({ key: key }, prop);
}

module.exports = (moduleDefinition) => {
    if (!moduleDefinition) {
        return [];
    }

    const definitionProps = moduleDefinition.reduce((accumulator, item) => {
        if (item.type === 'Property' && item.key.name === 'props') {
            accumulator = item.value.properties;
        }

        return accumulator;
    }, []);

    return definitionProps.map((prop) => {
        return parseProp(prop);
    });
};
