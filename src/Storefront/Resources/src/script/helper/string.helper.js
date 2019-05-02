export default class StringHelper {


    /**
     * turns first character of word to uppercase
     *
     * @param {string} string
     * @returns {string}
     * @private
     */
    static ucFirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }


    /**
     * turns first character of string to uppercase
     *
     * @param {string} string
     * @returns {string}
     * @private
     */
    static lcFirst(string) {
        return string.charAt(0).toLowerCase() + string.slice(1);
    }

    /**
     * converts a camel case string
     * into a dash case string
     *
     * @param string
     * @returns {string}
     */
    static toDashCase(string) {
        return string.replace(/([A-Z])/g, '-$1').replace(/^-/, '').toLowerCase();
    }

    /**
     *
     * @param {string} string
     * @param {string} separator
     *
     * @returns {string}
     */
    static toLowerCamelCase(string, separator) {
        const upperCamelCase = StringHelper.toUpperCamelCase(string, separator);
        return StringHelper.lcFirst(upperCamelCase);
    }

    /**
     *
     * @param {string} string
     * @param {string} separator
     *
     * @returns {string}
     */
    static toUpperCamelCase(string, separator) {
        if (!separator) {
            return StringHelper.ucFirst(string.toLowerCase());
        }

        const stringParts = string.split(separator);
        return stringParts.map(string => StringHelper.ucFirst(string.toLowerCase())).join('');
    }

    /**
     * returns primitive value of a string
     *
     * @param value
     * @returns {*}
     * @private
     */
    static parsePrimitive(value) {
        try {
            // replace comma with dot
            // if value only contains numbers and commas
            if (/^\d+(.|,)\d+$/.test(value)) {
                value = value.replace(',', '.');
            }

            return JSON.parse(value);
        }
        catch (e) {
            return value.toString();
        }
    }
}
