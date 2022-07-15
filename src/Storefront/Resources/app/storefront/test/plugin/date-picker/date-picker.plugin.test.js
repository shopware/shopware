import DatepickerPlugin from 'src/plugin/date-picker/date-picker.plugin';

describe('date-picker.plugin test', () => {

    let datepickerPlugin;
    let onOpenButtonClickSpy;
    let onCloseButtonClickSpy;
    let onClearButtonClickSpy;

    const datepickerPluginOptions = {
        selectors: {
            openButton: '.open',
            closeButton: '.close',
            clearButton: '.clear',
        },
    }

    const template = `
        <button class="open">Open date-picker</button>
        <button class="close">Close date-picker</button>
        <button class="clear">Reset date-picker</button>
        <input
            type="text"
            class="select-date"
            data-date-picker="true"
            value="2022-07-15T12:00:00+00:00"
        >
    `;

    function initPlugin(options = null) {
        return new DatepickerPlugin(document.querySelector('input.select-date'), {
            ...options,
            ...datepickerPluginOptions,
        });
    }

    beforeEach(() => {
        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => [],
                };
            },
        };

        document.body.innerHTML = template;

        // Spy plugin methods for later observation
        onOpenButtonClickSpy = jest.spyOn(DatepickerPlugin.prototype, 'onOpenButtonClick');
        onCloseButtonClickSpy = jest.spyOn(DatepickerPlugin.prototype, 'onCloseButtonClick');
        onClearButtonClickSpy = jest.spyOn(DatepickerPlugin.prototype, 'onClearButtonClick');

        datepickerPlugin = initPlugin();
    });

    afterEach(() => {
        document.body.innerHTML = '';

        datepickerPlugin = undefined;

        onOpenButtonClickSpy.mockClear();
        onCloseButtonClickSpy.mockClear();
        onClearButtonClickSpy.mockClear();
    });

    test('date-picker plugin exists', () => {
        expect(typeof datepickerPlugin).toBe('object');
    });

    test('date-picker should open when focus on input field', () => {
        const inputField = document.querySelector('.select-date.form-control');

        // Simulate user focus
        inputField.dispatchEvent(new Event('focus', { bubbles: true }));

        // Ensure flatpickr is in the DOM and has open class
        expect(document.querySelector('.flatpickr-calendar').classList.contains('open')).toBe(true);
    });

    test('date-picker should open using alternative button', () => {
        const button = document.querySelector('.open');

        // Simulate user click
        button.dispatchEvent(new Event('click', { bubbles: true }));

        // Ensure plugin method was called
        expect(onOpenButtonClickSpy).toHaveBeenCalledTimes(1);

        // Ensure flatpickr is in the DOM and has open class
        expect(document.querySelector('.flatpickr-calendar').classList.contains('open')).toBe(true);
    });

    test('date-picker should close using alternative button', () => {
        const openButton = document.querySelector('.open');
        const closeButton = document.querySelector('.close');

        // Open date-picker first
        openButton.dispatchEvent(new Event('click', { bubbles: true }));

        // Wow, a calendar. Let's close it again.
        closeButton.dispatchEvent(new Event('click', { bubbles: true }));

        // Ensure plugin method was called
        expect(onCloseButtonClickSpy).toHaveBeenCalledTimes(1);

        // Ensure flatpickr is still in the DOM but has no open class anymore
        expect(document.querySelector('.flatpickr-calendar').classList.contains('open')).toBe(false);
    });

    test('date-picker input should be cleared when clear button is used', () => {
        // Ensure hidden input has initial value
        expect(document.querySelector('.select-date.flatpickr-input').value).toBe('2022-07-15T12:00:00+00:00');

        const button = document.querySelector('.clear');

        // Simulate user click
        button.dispatchEvent(new Event('click', { bubbles: true }));

        // Ensure plugin method was called
        expect(onClearButtonClickSpy).toHaveBeenCalledTimes(1);

        // Ensure hidden input has cleared value and clear button is disabled
        expect(document.querySelector('.select-date.flatpickr-input').value).toBe('');
        expect(document.querySelector('.clear').disabled).toBe(true);
    });

    test('data-picker uses german locale options by default', () => {
        const localeOptions = datepickerPlugin.generateFlatpickrOptions();

        expect(localeOptions).toEqual(
            expect.objectContaining({
                altFormat: 'H:i',
                time_24hr: true,
                locale: expect.objectContaining({
                    weekdays: {
                        shorthand: expect.arrayContaining(['So', 'Mo', 'Di']),
                        longhand: expect.arrayContaining(['Sonntag', 'Montag', 'Dienstag']),
                    },
                    months: {
                        shorthand: expect.arrayContaining(['Jan', 'Feb', 'Mär']),
                        longhand: expect.arrayContaining(['Januar', 'Februar', 'März']),
                    },
                    firstDayOfWeek: 1,
                    weekAbbreviation: 'KW',
                }),
            }),
        );
    });

    test('data-picker uses english locale options when passed trough option', () => {
        datepickerPlugin = initPlugin({ locale: 'en' });

        const localeOptions = datepickerPlugin.generateFlatpickrOptions();

        expect(localeOptions).toEqual(
            expect.objectContaining({
                altFormat: 'h:i K',
                time_24hr: false,
                locale: expect.objectContaining({
                    weekdays: {
                        shorthand: expect.arrayContaining(['Sun', 'Mon', 'Tue']),
                        longhand: expect.arrayContaining(['Sunday', 'Monday', 'Tuesday']),
                    },
                    months: {
                        shorthand: expect.arrayContaining(['Jan', 'Feb', 'Mar']),
                        longhand: expect.arrayContaining(['January', 'February', 'March']),
                    },
                    firstDayOfWeek: 0,
                    weekAbbreviation: 'Wk',
                }),
            }),
        );
    });
});
