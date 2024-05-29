import NavbarPlugin from 'src/plugin/navbar/navbar.plugin';

describe('NavbarPlugin', () => {
    let navbarPlugin;
    let mockElement;
    let mockLink;

    beforeEach(() => {
        // Create a mock DOM environment
        mockElement = document.createElement('div');
        mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockElement.appendChild(mockLink);

        // Spy on addEventListener method
        jest.spyOn(mockLink, 'addEventListener');

        // Instantiate the NavbarPlugin with only one top-level link
        navbarPlugin = new NavbarPlugin(mockElement, {}, false); // Pass false to prevent init from being called
        navbarPlugin._topLevelLinks = [mockLink];
    });

    test('init should initialize _topLevelLinks', () => {
        // Create a new instance of NavbarPlugin inside the test
        navbarPlugin = new NavbarPlugin(mockElement, {}, false);
        navbarPlugin._topLevelLinks = [mockLink];

        // Clear the mock history of addEventListener
        mockLink.addEventListener.mockClear();

        navbarPlugin.init();

        expect(navbarPlugin._topLevelLinks).not.toBeNull();
        expect(mockLink.addEventListener).toHaveBeenCalledTimes(3);
    });

    test('_toggleNavbar should handle mouseenter and mouseleave events', () => {
        const mockEventEnter = {type: 'mouseenter'};
        const mockEventLeave = {type: 'mouseleave'};

        navbarPlugin._toggleNavbar = jest.fn();

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._toggleNavbar).toHaveBeenCalledWith(mockLink, mockEventEnter);

        navbarPlugin._toggleNavbar(mockLink, mockEventLeave);
        expect(navbarPlugin._toggleNavbar).toHaveBeenCalledWith(mockLink, mockEventLeave);
    });

    test('_navigateToLinkOnClick should prevent default action on click', () => {
        const mockEvent = {type: 'click', pageX: 1, preventDefault: jest.fn(), stopPropagation: jest.fn()};

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEvent);
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        expect(mockEvent.stopPropagation).toHaveBeenCalled();
    });

    test('_navigateToLinkOnClick should handle click event with pageX not equal to 0', () => {
        const mockEvent = {type: 'click', pageX: 1, preventDefault: jest.fn(), stopPropagation: jest.fn()};
        navbarPlugin._navigateToLinkOnClick(mockLink, mockEvent);
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        expect(mockEvent.stopPropagation).toHaveBeenCalled();
    });

    test('_closeAllDropdowns should close all dropdowns', () => {
        // Create mock dropdown instances
        const mockDropdown1 = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};
        const mockDropdown2 = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};

        // Mock window.bootstrap.Dropdown.getInstance to return the mock dropdown instances
        window.bootstrap = {Dropdown: {getInstance: jest.fn()}};
        window.bootstrap.Dropdown.getInstance.mockReturnValueOnce(mockDropdown1);
        window.bootstrap.Dropdown.getInstance.mockReturnValueOnce(mockDropdown2);

        // Mock _topLevelLinks to return two links
        navbarPlugin._topLevelLinks = [mockLink, mockLink];

        navbarPlugin._closeAllDropdowns();

        // Check if hide was called on both mock dropdown instances
        expect(mockDropdown1.hide).toHaveBeenCalled();
        expect(mockDropdown2.hide).toHaveBeenCalled();
    });

    test('_debounce should delay execution of function', () => {
        jest.useFakeTimers();

        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValue(false)}}
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
                getInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };

        // At this point in time, the callback passed to _debounce should not have been called yet
        expect(mockDropdown.show).not.toHaveBeenCalled();

        const mockEventEnter = {type: 'mouseenter'};
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        // Fast-forward until all timers have been executed
        jest.runAllTimers();

        // Now our callback should have been called!
        expect(mockDropdown.show).toHaveBeenCalled();

        const mockEventLeave = {type: 'mouseleave'};
        navbarPlugin._toggleNavbar(mockLink, mockEventLeave);

        expect(navbarPlugin._isMouseOver).toBe(false);
    });

    test('_clearDebounce should clear the debounce timer', () => {
        jest.useFakeTimers();

        const callback = jest.fn();
        navbarPlugin._debounce(callback, navbarPlugin.options.debounceTime);
        navbarPlugin._clearDebounce();

        jest.runOnlyPendingTimers();
        expect(callback).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should set _isMouseOver to true on mouseenter', () => {
        const mockEventEnter = {type: 'mouseenter'};
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._isMouseOver).toBe(true);
    });

    test('_toggleNavbar should call _debounce on mouseenter', () => {
        const mockEventEnter = {type: 'mouseenter'};
        navbarPlugin._debounce = jest.fn();
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._debounce).toHaveBeenCalled();
    });

    test('_closeAllDropdowns should call hide on dropdowns with show class', () => {
        const mockDropdown = {hide: jest.fn(), _menu: {classList: {contains: jest.fn().mockReturnValue(true)}}};
        window.bootstrap.Dropdown.getInstance.mockReturnValue(mockDropdown);
        navbarPlugin._topLevelLinks = [mockLink];
        navbarPlugin._closeAllDropdowns();
        expect(mockDropdown.hide).toHaveBeenCalled();
    });
});
