/* eslint-disable */
import SearchPlugin from 'src/plugin/header/search-widget.plugin';
import FocusHandler from 'src/helper/focus-handler.helper';

describe('SearchPlugin Tests', () => {
    let searchPlugin = undefined;
    let formElement = null;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
            </form>
        `;

        formElement = document.getElementById('search-widget');

        window.focusHandler = new FocusHandler();

        searchPlugin = new SearchPlugin(formElement);
    });

    afterEach(() => {
        searchPlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('search plugin exists', () => {
        expect(typeof searchPlugin).toBe('object');
    });

    test('_handleSearchEvent should preventDefault and stopPropagation', () => {
        searchPlugin._inputField = {
            value: 'ab'
        }
        const eventMock = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };
        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();

        searchPlugin._handleSearchEvent(eventMock);

        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(eventMock.stopPropagation).toHaveBeenCalled();
    });

    test('_registerInputFocus should warn if searchWidgetCollapseButton dosn\'t exist', () => {
        console.warn = jest.fn();

        searchPlugin._registerInputFocus()
        expect(console.warn).toHaveBeenCalledWith(`Called selector '${searchPlugin.options.searchWidgetCollapseButtonSelector}' for the search toggle button not found. Autofocus has been disabled on mobile.`)
    });

    test('_handleSearchEvent should not preventDefault and stopPropagation', () => {
        searchPlugin._inputField = {
            value: 'abcd'
        }
        const eventMock = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };
        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();

        searchPlugin._handleSearchEvent(eventMock);

        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();
    });

    test('_handleSearchEvent should preventDefault and stopPropagation', () => {
        searchPlugin._inputField = {
            value: '         '
        }
        const eventMock = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };
        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();

        searchPlugin._handleSearchEvent(eventMock);

        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(eventMock.stopPropagation).toHaveBeenCalled();
    });

    test('_handleInputEvent should clearSuggestResult', () => {
        searchPlugin._inputField = {
            value: '         '
        }
        searchPlugin._clearSuggestResults = jest.fn();
        searchPlugin._suggest = jest.fn();
        searchPlugin.$emitter.publish = jest.fn();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();

        searchPlugin._handleInputEvent();

        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();
    });

    test('_handleInputEvent should not clearSuggestResult and publish handleInputEvent', () => {
        searchPlugin._inputField = {
            value: 'abcde'
        }
        searchPlugin._clearSuggestResults = jest.fn();
        searchPlugin._suggest = jest.fn();
        searchPlugin.$emitter.publish = jest.fn();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();

        searchPlugin._handleInputEvent();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('handleInputEvent', { "value": "abcde" });
    });

    test('_handleInputEvent should clearSuggestResult and not publish handleInputEvent because of trim', () => {
        searchPlugin._inputField = {
            value: 'ab  '
        }
        searchPlugin._clearSuggestResults = jest.fn();
        searchPlugin._suggest = jest.fn();
        searchPlugin.$emitter.publish = jest.fn();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();

        searchPlugin._handleInputEvent();

        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();
    });

    test('_handleInputEvent should not clearSuggestResult and publish handleInputEvent and whitespaces being removed', () => {
        searchPlugin._inputField = {
            value: '  abcd   '
        }
        searchPlugin._clearSuggestResults = jest.fn();
        searchPlugin._suggest = jest.fn();
        searchPlugin.$emitter.publish = jest.fn();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).not.toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalled();

        searchPlugin._handleInputEvent();

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
        expect(searchPlugin._suggest).toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('handleInputEvent', { "value": "abcd" });
    });

    test('_handleKeyEvent should focus first search result item when pressing ArrowDown', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result">
                    <div class="js-result">
                        <a href="#">First Result</a>
                    </div>
                </div>
            </form>
        `;

        formElement = document.getElementById('search-widget');
        searchPlugin = new SearchPlugin(formElement);

        const eventMock = {
            key: 'ArrowDown',
            preventDefault: jest.fn()
        };

        searchPlugin._inputField.value = 'test';
        searchPlugin._handleKeyEvent(eventMock);

        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(document.activeElement.textContent).toBe('First Result');
    });

    test('_handleKeyEvent should not focus when input is empty', () => {
        const eventMock = {
            key: 'ArrowDown',
            preventDefault: jest.fn()
        };

        searchPlugin._inputField.value = '';
        searchPlugin._handleKeyEvent(eventMock);

        expect(eventMock.preventDefault).not.toHaveBeenCalled();
    });

    test('_handleSearchItemKeyEvent should move focus up and down', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result">
                    <div class="js-result">
                        <a href="#">First Result</a>
                    </div>
                    <div class="js-result">
                        <a href="#">Second Result</a>
                    </div>
                    <div class="js-result">
                        <a href="#">Third Result</a>
                    </div>
                </div>
            </form>
        `;

        formElement = document.getElementById('search-widget');
        searchPlugin = new SearchPlugin(formElement);

        const secondResult = document.querySelectorAll('.js-result')[1].querySelector('a');
        const eventMock = {
            key: 'ArrowDown',
            target: secondResult,
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            stopImmediatePropagation: jest.fn()
        };

        // Test moving down
        searchPlugin._handleSearchItemKeyEvent(eventMock);
        expect(document.activeElement.textContent).toBe('Third Result');

        // Test moving up
        eventMock.key = 'ArrowUp';
        searchPlugin._handleSearchItemKeyEvent(eventMock);
        expect(document.activeElement.textContent).toBe('First Result');

        // Test moving up from first item returns to input
        eventMock.target = document.querySelector('.js-result').querySelector('a');
        searchPlugin._handleSearchItemKeyEvent(eventMock);
        expect(document.activeElement).toBe(searchPlugin._inputField);
    });

    test('_handleSearchItemKeyEvent should not handle non-arrow keys', () => {
        const eventMock = {
            key: 'Enter',
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            stopImmediatePropagation: jest.fn()
        };

        searchPlugin._handleSearchItemKeyEvent(eventMock);

        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();
        expect(eventMock.stopImmediatePropagation).not.toHaveBeenCalled();
    });

    test('Click on close button should clear input and hide results', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result"></div>
            </form>
        `;

        formElement = document.getElementById('search-widget');
        searchPlugin = new SearchPlugin(formElement);

        searchPlugin._inputField.value = 'test';
        searchPlugin._clearSuggestResults = jest.fn();

        const clickEvent = new Event('click');
        searchPlugin._closeButton.dispatchEvent(clickEvent);

        expect(searchPlugin._inputField.value).toBe('');
        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
    });
});


