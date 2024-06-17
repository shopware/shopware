/* eslint-disable */
import FormPreserverPlugin from 'src/plugin/forms/form-preserver.plugin';
import Storage from 'src/helper/storage/storage.helper';
import template from './form-preserver.plugin.template.html';

/**
 * @package content
 */
describe('Form Preserver tests', () => {
    let formPreserverPlugin = undefined;
    const testValuesNormal = {
        colortest: '#123456',
        datetest: '2018-06-12',
        'datetime-localtest': '2018-06-12T10:08',
        monthtest: '2018-06',
        numbertest: '6',
        teltest: '0123456789',
        texttest: 'atext',
        timetest: '10:08',
        urltest: 'https://www.shopware.com/',
        weektest: '2018-W52',
        cars: 'saab',
        textareatest: 'a\nlonger\ntext',
        nestedtest: 'text from nested input field'
    };

    beforeEach(() => {
        document.body.innerHTML = template;

        document.$emitter = {
            unsubscribe: () => {},
            subscribe: () => {},
        };

        Storage.clear();

        const form = document.querySelector('#test');
        formPreserverPlugin = new FormPreserverPlugin(form);
    });

    afterEach(() => {
        formPreserverPlugin = undefined;
    });

    test('form preserver plugin exists', () => {
        expect(typeof formPreserverPlugin).toBe('object');
    });

    test('form preserver finds all form elements', () => {
        expect(formPreserverPlugin.formElements.length).toBe(29);
    });

    test('register event is only called for valid elements', () => {
        formPreserverPlugin._registerFormElementEvent = jest.fn();

        expect(formPreserverPlugin._registerFormElementEvent).toHaveBeenCalledTimes(0);

        formPreserverPlugin._prepareElements();

        expect(formPreserverPlugin._registerFormElementEvent).toHaveBeenCalledTimes(29 - 10);

        formPreserverPlugin._registerFormElementEvent.mockClear();
    });

    test('set values from storage to normal elements', () => {
        Object.entries(testValuesNormal).forEach(([key, value]) => {
            Storage.setItem(`test.${key}`, value);
        });

        formPreserverPlugin._prepareElements();

        Object.entries(testValuesNormal).forEach(([key, value]) => {
            const element = document.querySelector(`[name=${key}]`);
            expect(element.value).toBe(value);
        });
    });

    test('set values from storage to checkbox elements', () => {
        Storage.setItem(`test.checkboxtest`, true);

        formPreserverPlugin._prepareElements();

        const element = document.querySelector(`[name=checkboxtest]`);
        expect(element.checked).toBe(true);
    });

    test('set values from storage to radio elements', () => {
        Storage.setItem(`test.radiotest`, 'radio2');

        formPreserverPlugin._prepareElements();

        const elements = document.querySelectorAll(`[name=radiotest]`);
        elements.forEach(element => {
            expect(element.checked).toBe(element.value === 'radio2');
        });
    });

    test('set values from storage to multiselect elements', () => {
        Storage.setItem(`test.cars2`, 'volvo,audi');

        formPreserverPlugin._prepareElements();

        const element = document.querySelector(`[name=cars2]`);
        const selectedOptions = Array.from(element.selectedOptions).map(option => option.value);
        expect(selectedOptions.sort()).toEqual(['audi','volvo']);
    });

    test('set values to storage from normal elements', () => {
        jest.useFakeTimers();

        Object.entries(testValuesNormal).forEach(([key, value]) => {
            const element = document.querySelector(`[name=${key}]`);
            element.value = value;
            element.dispatchEvent(new Event('change'));
            element.dispatchEvent(new Event('input'));
        });

        jest.advanceTimersByTime(formPreserverPlugin.delay);

        Object.entries(testValuesNormal).forEach(([key, value]) => {
            expect(Storage.getItem(`test.${key}`)).toBe(value);
        });
    });

    test('set values to storage from checkbox elements', () => {
        const element = document.querySelector(`[name=checkboxtest]`);
        element.checked = true;
        element.dispatchEvent(new Event('change'));

        expect(Storage.getItem(`test.checkboxtest`)).toBe('true');
    });

    test('set values to storage from radio elements', () => {
        const elements = document.querySelectorAll(`[name=radiotest]`);
        elements.forEach(element => {
            element.checked = element.value === 'radio2';
            element.dispatchEvent(new Event('change'));
        });

        expect(Storage.getItem(`test.radiotest`)).toBe('radio2');
    });

    test('set values to storage from multiselect elements', () => {
        const element = document.querySelector(`[name=cars2]`);
        Object.entries(element.options).forEach(([key, option]) => {
            option.selected = option.value === 'audi' || option.value === 'volvo';
        });
        element.dispatchEvent(new Event('change'));

        expect(Storage.getItem(`test.cars2`)).toBe('volvo,audi');
    });

    test.each(['submit', 'reset'])('form preserver clears storage on form submission / reset', (eventType) => {
        const element = document.querySelector(`[name=checkboxtest]`);
        element.checked = true;
        element.dispatchEvent(new Event('change'));

        expect(Storage.getItem(`test.checkboxtest`)).toBe('true');

        const form = document.querySelector('#test');
        form.dispatchEvent(new Event(eventType));

        expect(Storage.getItem('test.checkboxtest')).toBeNull();
    });
});
