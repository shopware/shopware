import Iterator from 'src/helper/iterator.helper';

/**
 * This utility serializes a form via the FormData class
 *
 * @package content
 */
export default class FormSerializeUtil {

    /**
     * serializes a form
     *
     * @param {HTMLFormElement} form
     * @param {boolean} strict
     *
     * @returns {*}
     */
    static serialize(form, strict = true) {

        if (form.nodeName !== 'FORM') {
            if (strict) {
                throw new Error('The passed element is not a form!');
            }

            return {};
        }

        return new FormData(form);
    }

    /**
     *
     * serializes the form and returns
     * its data as json
     *
     * @param {HTMLFormElement} form
     * @param {boolean} strict
     * @returns {*}
     */
    static serializeJson(form, strict = true) {
        const formData = FormSerializeUtil.serialize(form, strict);
        if (Object.keys(formData).length === 0) {
            return {};
        }
        const json = {};

        Iterator.iterate(formData, (value, key) => json[key] = value);

        return json;
    }
}
