/* eslint-disable */
import SearchPlugin from 'src/plugin/header/search-widget.plugin';
import FocusHandler from 'src/helper/focus-handler.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

describe('SearchPlugin Tests', () => {
    let searchPlugin = undefined;
    let formElement = null;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
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
        jest.clearAllMocks();
    });

    test('search plugin exists', () => {
        expect(typeof searchPlugin).toBe('object');
    });

    test('_handleSearchEvent should preventDefault and stopPropagation', () => {
        searchPlugin._inputField.value = 'ab';

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

    test('_handleSearchEvent should not preventDefault and stopPropagation', () => {
        searchPlugin._inputField.value = 'abcd';

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
        searchPlugin._inputField.value = '         ';

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
        searchPlugin._inputField.value = '         ';
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
        searchPlugin._inputField.value = 'abcde';
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
        searchPlugin._inputField.value = 'ab  ';
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
        searchPlugin._inputField.value = '  abcd   ';
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
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
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

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);

        const eventMock = {
            key: 'ArrowDown',
            preventDefault: jest.fn()
        };

        searchPlugin._inputField.value = 'test';
        const searchSuggest = document.querySelector('.js-search-result');
        searchPlugin.searchSuggestLinks = Array.from(window.focusHandler.getFocusableElements(searchSuggest));
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
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
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

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);

        const searchSuggest = document.querySelector('.js-search-result');
        searchPlugin.searchSuggestLinks = Array.from(window.focusHandler.getFocusableElements(searchSuggest));

        const secondResult = searchPlugin.searchSuggestLinks[1];
        const eventMock = {
            key: 'ArrowDown',
            target: secondResult,
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            stopImmediatePropagation: jest.fn()
        };

        // Test moving down
        searchPlugin._handleSearchItemKeyEvent(1, eventMock);
        expect(document.activeElement.textContent).toBe('Third Result');
        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(eventMock.stopPropagation).toHaveBeenCalled();
        expect(eventMock.stopImmediatePropagation).toHaveBeenCalled();

        // Test moving up
        eventMock.key = 'ArrowUp';
        searchPlugin._handleSearchItemKeyEvent(2, eventMock);
        expect(document.activeElement.textContent).toBe('Second Result');
        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(eventMock.stopPropagation).toHaveBeenCalled();
        expect(eventMock.stopImmediatePropagation).toHaveBeenCalled();

        // Test moving up from first item returns to input
        eventMock.target = searchPlugin.searchSuggestLinks[0];
        searchPlugin._handleSearchItemKeyEvent(0, eventMock);
        expect(document.activeElement).toBe(searchPlugin._inputField);
        expect(eventMock.preventDefault).toHaveBeenCalled();
        expect(eventMock.stopPropagation).toHaveBeenCalled();
        expect(eventMock.stopImmediatePropagation).toHaveBeenCalled();
    });

    test('_handleSearchItemKeyEvent should not handle non-arrow keys', () => {
        const eventMock = {
            key: 'Enter',
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            stopImmediatePropagation: jest.fn()
        };

        searchPlugin._handleSearchItemKeyEvent(0, eventMock);

        expect(eventMock.preventDefault).not.toHaveBeenCalled();
        expect(eventMock.stopPropagation).not.toHaveBeenCalled();
        expect(eventMock.stopImmediatePropagation).not.toHaveBeenCalled();
    });

    test('Click on close button should clear input and hide results', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" class="js-search-form">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result"></div>
            </form>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);

        searchPlugin._inputField.value = 'test';
        searchPlugin._clearSuggestResults = jest.fn();

        const clickEvent = new Event('click');
        searchPlugin._closeButton.dispatchEvent(clickEvent);

        expect(searchPlugin._inputField.value).toBe('');
        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
    });

    test('_suggest should handle successful AJAX request', async () => {
        const mockResponse = '<div class="js-search-result"><div class="js-result"><a href="#">Test Result</a></div></div>';
        global.fetch = jest.fn().mockResolvedValue({
            text: () => Promise.resolve(mockResponse)
        });

        searchPlugin._inputField.value = 'test';
        searchPlugin.$emitter.publish = jest.fn();

        await searchPlugin._suggest('test');

        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('beforeSearch');
        expect(global.fetch).toHaveBeenCalledWith('/searchtest', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        await new Promise(process.nextTick);
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('afterSuggest');
        expect(searchPlugin._inputField.getAttribute('aria-expanded')).toBe('true');
        expect(searchPlugin.searchSuggestLinks.length).toBe(1);
    });

    test('_suggest should handle failed AJAX request', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));
        searchPlugin._inputField.value = 'test';
        searchPlugin.$emitter.publish = jest.fn();
        searchPlugin._clearSuggestResults = jest.fn();

        await searchPlugin._suggest('test');

        expect(global.fetch).toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('beforeSearch');

        await new Promise(process.nextTick);
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalledWith('afterSuggest');
        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
    });

    test('_onBodyClick should clear results when clicking outside', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result"></div>
            </form>
            <div id="outside">Outside content</div>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);
        searchPlugin._clearSuggestResults = jest.fn();

        const clickEvent = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            view: window,
            target: document.getElementById('outside')
        });

        document.body.dispatchEvent(clickEvent);

        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
    });

    test('_onBodyClick should not clear results when clicking inside search form', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result"></div>
            </form>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);
        searchPlugin._clearSuggestResults = jest.fn();

        const clickEvent = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        searchPlugin._inputField.dispatchEvent(clickEvent);

        expect(searchPlugin._clearSuggestResults).not.toHaveBeenCalled();
    });

    test('_onCloseButtonClick should clear input and results', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result"></div>
            </form>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);

        searchPlugin._inputField.value = 'test';
        searchPlugin._clearSuggestResults = jest.fn();

        const clickEvent = new Event('click');
        searchPlugin._closeButton.dispatchEvent(clickEvent);

        expect(searchPlugin._inputField.value).toBe('');
        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
    });

    test('_registerInputFocus should handle mobile focus', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
                <input type="search" name="search" autocapitalize="off" autocomplete="off">
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <button type="button" class="js-search-toggle-btn">Toggle</button>
            </form>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);
        searchPlugin._inputField.focus = jest.fn();

        const toggleButton = document.querySelector('.js-search-toggle-btn');
        const clickEvent = new Event('click', {
            bubbles: true,
            cancelable: true,
        });
        toggleButton.dispatchEvent(clickEvent);

        expect(searchPlugin._inputField.focus).toHaveBeenCalled();
    });
});
