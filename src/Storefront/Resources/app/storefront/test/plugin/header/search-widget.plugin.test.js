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

    test('_handleSearchEvent dispatches product:search-performed event on form submit when term is valid', () => {
        const listener = jest.fn();
        document.addEventListener('product:search-performed', listener);
        searchPlugin._inputField.value = 'shoes';

        searchPlugin._handleSearchEvent({
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
        });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual({ term: 'shoes' });

        document.removeEventListener('product:search-performed', listener);
    });

    test('_handleSearchEvent does not dispatch event when term is below minimum length', () => {
        const listener = jest.fn();
        document.addEventListener('product:search-performed', listener);
        searchPlugin._inputField.value = 'ab';

        searchPlugin._handleSearchEvent({
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
        });

        expect(listener).not.toHaveBeenCalled();

        document.removeEventListener('product:search-performed', listener);
    });

    test('_handleSuggestResultClick dispatches product:search-suggestion-product-viewed when clicking a product link', () => {
        const listener = jest.fn();
        document.addEventListener('product:search-suggestion-product-viewed', listener);
        searchPlugin._inputField.value = 'shoes';

        const link = document.createElement('a');
        link.setAttribute('href', '/product/123');
        const inner = document.createElement('span');
        link.appendChild(inner);

        searchPlugin._handleSuggestResultClick({ target: inner });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual({ term: 'shoes' });

        document.removeEventListener('product:search-suggestion-product-viewed', listener);
    });

    test('_handleSuggestResultClick dispatches product:search-performed when clicking the show-all-results link', () => {
        const listener = jest.fn();
        document.addEventListener('product:search-performed', listener);
        searchPlugin._inputField.value = 'shoes';

        const totalContainer = document.createElement('li');
        totalContainer.className = 'search-suggest-total';
        const link = document.createElement('a');
        link.setAttribute('href', '/search?search=shoes');
        link.className = 'search-suggest-total-link';
        totalContainer.appendChild(link);

        searchPlugin._handleSuggestResultClick({ target: link });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual({ term: 'shoes' });

        document.removeEventListener('product:search-performed', listener);
    });

    test('_handleSuggestResultClick ignores clicks that are not on a link', () => {
        const productListener = jest.fn();
        const performedListener = jest.fn();
        document.addEventListener('product:search-suggestion-product-viewed', productListener);
        document.addEventListener('product:search-performed', performedListener);
        searchPlugin._inputField.value = 'shoes';

        const nonLink = document.createElement('div');

        searchPlugin._handleSuggestResultClick({ target: nonLink });

        expect(productListener).not.toHaveBeenCalled();
        expect(performedListener).not.toHaveBeenCalled();

        document.removeEventListener('product:search-suggestion-product-viewed', productListener);
        document.removeEventListener('product:search-performed', performedListener);
    });

    test('_suggest should handle successful AJAX request', async () => {
        const mockResponse = `
            <div class="search-suggest js-search-result">
                <ul id="search-suggest-listbox">
                    <li class="js-result">
                        <a href="#">Test Result</a>
                    </li>
                    <li id="search-suggest-result-info">1 result</li>
                </ul>
            </div>
        `;
        global.fetch = jest.fn().mockResolvedValue({
            text: () => Promise.resolve(mockResponse)
        });

        searchPlugin._inputField.value = 'test';
        searchPlugin.$emitter.publish = jest.fn();

        const suggestionListener = jest.fn();
        document.addEventListener('product:search-suggestion-shown', suggestionListener);

        await searchPlugin._suggest('test');

        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('beforeSearch');
        expect(global.fetch).toHaveBeenCalledWith('/searchtest', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        await new Promise(process.nextTick);
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('afterSuggest');
        expect(searchPlugin._inputField.getAttribute('aria-expanded')).toBe('true');
        expect(searchPlugin._inputField.getAttribute('aria-controls')).toBe('search-suggest-listbox');
        expect(searchPlugin._inputField.getAttribute('aria-describedby')).toBe('search-suggest-result-info');
        expect(searchPlugin.searchSuggestLinks.length).toBe(1);

        expect(suggestionListener).toHaveBeenCalledTimes(1);
        expect(suggestionListener.mock.calls[0][0].detail).toEqual({ term: 'test' });

        document.removeEventListener('product:search-suggestion-shown', suggestionListener);
    });

    test('_clearSuggestResults should remove dynamic accessibility references', () => {
        document.body.innerHTML = `
            <form id="search-widget" data-search-widget="true" data-url="/search" class="js-search-form">
                <input
                    type="search"
                    name="search"
                    autocapitalize="off"
                    autocomplete="off"
                    aria-controls="search-suggest-listbox"
                    aria-describedby="search-suggest-result-info"
                >
                <button type="submit" class="btn header-search-btn">Search</button>
                <button type="button" class="btn header-close-btn js-search-close-btn d-none"></button>
                <div class="search-suggest js-search-result">
                    <ul id="search-suggest-listbox">
                        <li id="search-suggest-result-info">1 result</li>
                    </ul>
                </div>
            </form>
        `;

        const formElement = document.getElementById('search-widget');
        const searchPlugin = new SearchPlugin(formElement);
        searchPlugin.$emitter.publish = jest.fn();

        searchPlugin._clearSuggestResults();

        expect(searchPlugin._inputField.hasAttribute('aria-controls')).toBe(false);
        expect(searchPlugin._inputField.hasAttribute('aria-describedby')).toBe(false);
        expect(searchPlugin._inputField.getAttribute('aria-expanded')).toBe('false');
        expect(document.querySelector('.js-search-result')).toBeNull();
    });

    test('_suggest should handle failed AJAX request', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));
        searchPlugin._inputField.value = 'test';
        searchPlugin.$emitter.publish = jest.fn();
        searchPlugin._clearSuggestResults = jest.fn();

        const suggestionListener = jest.fn();
        document.addEventListener('product:search-suggestion-shown', suggestionListener);

        await searchPlugin._suggest('test');

        expect(global.fetch).toHaveBeenCalled();
        expect(searchPlugin.$emitter.publish).toHaveBeenCalledWith('beforeSearch');

        await new Promise(process.nextTick);
        expect(searchPlugin.$emitter.publish).not.toHaveBeenCalledWith('afterSuggest');
        expect(searchPlugin._clearSuggestResults).toHaveBeenCalled();
        expect(suggestionListener).not.toHaveBeenCalled();

        document.removeEventListener('product:search-suggestion-shown', suggestionListener);
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
