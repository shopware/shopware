/* eslint-disable */
import ClearInput from 'src/plugin/clear-input-button/clear-input.plugin';

describe('ClearInput plugin tests', () => {
    let clearInput;
    let textarea;
    const buttonSelector = 'clear-textarea-mock-id';
    const clearInputOptions = {
        clearButtonSelector: `#${buttonSelector}`
    };

    beforeEach(() => {
        document.body.innerHTML = `<textarea></textarea><button id="${buttonSelector}"></button>`;
        textarea = document.body.querySelector('textarea');
        clearInput = new ClearInput(textarea, clearInputOptions);
    });

    afterEach(() => {
        clearInput = undefined;
        textarea.remove();
    });

    test('clearInput plugin exists', () => {
        expect(typeof clearInput).toBe('object');
    });

    test('clearInput() gets called on click, when button is enabled', () => {
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(true);

        // Observe method, which has to be called
        const spyMethod = jest.spyOn(clearInput, 'onInputChange');

        // Input text and simulate throw change event
        clearInput.el.value = 'Hello';
        clearInput.onInputChange();

        // Check usage of disable method
        expect(spyMethod).toHaveBeenCalled();
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(false);

        // simulate click, which disables the clear button again
        clearInput.clearButtons[0].click();
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(true);
    });

    test('enable and disable clearButton, when only the text changes', () => {
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(true);

        // Input text and simulate throw change event
        clearInput.el.value = 'Hello';
        clearInput.onInputChange();
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(false);

        // Remove and simulate throw change event again
        clearInput.el.value = '';
        clearInput.onInputChange();
        expect(clearInput.clearButtons[0].hasAttribute('disabled')).toBe(true);
    });
});
