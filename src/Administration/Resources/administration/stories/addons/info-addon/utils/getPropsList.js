import constructorToString from './constructorToString';

/**
 * Parses the props of a component and prepares it for the output in storybook
 *
 * @param {Object} props
 * @returns {Array}
 */
function getPropsList(props) {
    if (!props) {
        return [];
    }

    return Object.keys(props).map((propName) => {
        const prop = props[propName];
        let defaultVal = 'null';
        let required = false;

        if (Object.prototype.hasOwnProperty.call(prop, 'default') && prop.default) {
            defaultVal = prop.default.toString();
        }

        if (Object.prototype.hasOwnProperty.call(prop, 'required')) {
            required = prop.required;
        }

        return {
            name: propName,
            type: constructorToString(prop.type),
            default: defaultVal,
            required
        };
    });
}

export default getPropsList;
