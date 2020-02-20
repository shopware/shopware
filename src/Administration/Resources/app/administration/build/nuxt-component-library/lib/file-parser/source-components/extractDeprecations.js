module.exports = (moduleDefinition) => {
    if (!moduleDefinition && !Array.isArray(moduleDefinition)) {
        return null;
    }

    const deprecationProperty = moduleDefinition.find(item => item.type === 'Property' && item.key.name === 'deprecated');

    if (!deprecationProperty || deprecationProperty.type !== 'Property' || deprecationProperty.key.name !== 'deprecated') {
        return null;
    }

    if (deprecationProperty.value.type === 'Literal') {
        return {
            version: deprecationProperty.value.value,
            comment: ''
        };
    }

    if (deprecationProperty.value.type === 'ObjectExpression') {
        const [version, comment] = deprecationProperty.value.properties;

        return {
            version: version.value.value,
            comment: comment.value.value
        };
    }

    return null;
};
