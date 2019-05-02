/**
 * this utility serializes a form
 */
export default class FormSerializeUtil {

    /**
     * serializes a form
     *
     * @param {HTMLElement} form
     * @param {boolean} strict
     *
     * @returns {*}
     */
    static serialize(form, strict = true) {
        let serialized = {};

        if (form.nodeName !== 'FORM') {
            if (strict) {
                throw new Error('The passed element is not a form!');
            }

            return serialized;
        }

        form.elements.forEach(element => {
            serialized = FormSerializeUtil._serializeElement(serialized, element);
        });

        return serialized;
    }

    /**
     * serializes a form element
     *
     * @param {*} serialized
     * @param {HTMLElement} element
     *
     * @returns {*}
     *
     * @private
     */
    static _serializeElement(serialized, element) {
        if (!element.name || element.disabled) {
            return serialized;
        }

        switch (element.type) {
            case 'file':
            case 'reset':
            case'submit':
            case 'button':
                // don't parse invalid fields
                break;
            case 'checkbox':
            case 'radio':
                if (element.checked === false) {
                    break;
                }
                serialized[element.name] = element.value;
                break;
            case 'select-multiple':
                serialized = FormSerializeUtil._serializeMultiElement(serialized, element);
                break;
            default:
                serialized[element.name] = element.value;
                break;
        }

        return serialized;
    }


    /**
     * serializes a multiple choice element
     *
     * @param {*} serialized
     * @param {HTMLElement} element
     */
    static _serializeMultiElement(serialized, element) {
        element.options.forEach(option => {
            if (option.selected) {
                serialized[element.name] = option.value;
            }
        });

        return serialized;
    }
}
