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
        if (!(formData instanceof FormData) && Object.keys(formData).length === 0) {
            return {};
        }

        if (formData instanceof FormData && Array.from(formData.entries()).length === 0) {
            return {};
        }

        const json = {};

        for (const [key, value] of formData.entries()) {
            json[key] = value;
        }

        return json;
    }
}
