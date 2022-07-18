/* eslint-disable */
import SearchPlugin from 'src/plugin/header/search-widget.plugin';

describe('ListingPlugin tests', () => {
    let searchPlugin = undefined;
    let spyInit = jest.fn();
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // mock search plugin
        const mockElement = document.createElement('div');
        searchPlugin = new SearchPlugin(mockElement);
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
});


