import DeviceDetection from 'src/helper/device-detection.helper';
import FocusHandler from 'src/helper/focus-handler.helper';
import NavbarPlugin from 'src/plugin/navbar/navbar.plugin';

describe('NavbarPlugin', () => {
    let navbarPlugin;
    let mockElement;
    let mockLink;

    beforeEach(() => {
        jest.spyOn(DeviceDetection, 'isTouchDevice').mockReturnValue(false);
        // Create a mock DOM environment
        mockElement = document.createElement('div');
        mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.href = '#';
        mockElement.appendChild(mockLink);

        // Spy on addEventListener method and window open method
        jest.spyOn(mockLink, 'addEventListener');
        jest.spyOn(window, 'open').mockImplementation(() => {});

        // Instantiate the NavbarPlugin with only one top-level link
        navbarPlugin = new NavbarPlugin(mockElement, {}, false); // Pass false to prevent init from being called
        navbarPlugin._topLevelLinks = [mockLink];
    });

    afterEach(() => {
        // Clear the mock for window.open
        window.open.mockRestore();
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

    test('init should omit click event for elements without a reference', () => {
        const noHrefElement = document.createElement('div');
        const noHrefLink = document.createElement('a');
        noHrefLink.classList.add('main-navigation-link');
        noHrefElement.appendChild(noHrefLink);
        jest.spyOn(noHrefLink, 'addEventListener');

        navbarPlugin = new NavbarPlugin(noHrefElement);

        const addedEvents = {};
        noHrefLink.addEventListener.mock.calls.forEach(call => {
            addedEvents[call[0]] = call[1];
        });

        expect(navbarPlugin._topLevelLinks).not.toBeNull();
        expect(noHrefLink.addEventListener).toHaveBeenCalledTimes(2);

        expect(addedEvents['mouseenter']).toBeDefined();
        expect(typeof addedEvents['mouseenter']).toBe('function');

        expect(addedEvents['mouseleave']).toBeDefined();
        expect(typeof addedEvents['mouseleave']).toBe('function');

        expect(addedEvents['click']).toBeUndefined();
    });

    test('_navigateToLinkOnClick should open in new window when target blank is set on a dropdown link', () => {
        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = {
            href: 'https://example.com',
            target: '_blank',
            classList: { contains: jest.fn().mockReturnValue(true) },
        };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(window.open).toHaveBeenCalledWith(mockLink.href, '_blank', 'noopener, noreferrer');
    });

    test('_navigateToLinkOnClick should not open a new window for a plain target blank link (native navigation handles it)', () => {
        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = {
            href: 'https://example.com',
            target: '_blank',
            classList: { contains: jest.fn().mockReturnValue(false) },
        };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(window.open).not.toHaveBeenCalled();
    });

    test('_navigateToLinkOnClick should set window.location.href if not target _blank', () => {
        const navigateToSpy = jest.spyOn(NavbarPlugin.prototype, '_navigateTo').mockImplementation(() => {});

        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = {
            href: 'https://example.com/abc',
            target: '_self',
            classList: { contains: jest.fn().mockReturnValue(true) },
        };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(mockLink.classList.contains).toHaveBeenCalledWith('dropdown-toggle');
        expect(navigateToSpy).toHaveBeenCalledWith('https://example.com/abc');
    });

    test('_navigateToLinkOnClick should not redirect manually when link has no dropdown', () => {
        const navigateToSpy = jest.spyOn(NavbarPlugin.prototype, '_navigateTo').mockImplementation(() => {});

        const mockEventClick = { type: 'click', pageX: 99 };
        const mockLink = {
            href: 'https://example.com/abc',
            target: '_self',
            classList: { contains: jest.fn().mockReturnValue(false) },
        };

        navbarPlugin._navigateToLinkOnClick(mockLink, mockEventClick);

        expect(mockLink.classList.contains).toHaveBeenCalledWith('dropdown-toggle');
        expect(navigateToSpy).not.toHaveBeenCalled();
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
            _menu: { classList: { contains: jest.fn().mockReturnValue(false) } },
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
        const mockDropdown = {_menu: {classList: {contains: jest.fn().mockReturnValue(false)}}};
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._isMouseOver).toBe(true);
    });

    test('_toggleNavbar should call _debounce on mouseenter', () => {
        const mockEventEnter = {type: 'mouseenter'};
        const mockDropdown = {_menu: {classList: {contains: jest.fn().mockReturnValue(false)}}};
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };
        navbarPlugin._debounce = jest.fn();
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);
        expect(navbarPlugin._debounce).toHaveBeenCalled();
    });

    test('current page is applied during initialization', () => {
        jest.spyOn(navbarPlugin, '_setCurrentPage'); // Spy on the method

        navbarPlugin.init();

        expect(navbarPlugin._setCurrentPage).toHaveBeenCalled();
    });

    test('active class and aria-current is set for one nav-item', () => {
        const mockLink = document.createElement('a');
        mockLink.classList.add('nav-item-1-link');
        mockLink.setAttribute('href', 'https://example.com');
        mockElement.appendChild(mockLink);

        window.activeNavigationId = 1; // Set the activeNavigationId

        navbarPlugin._setCurrentPage();

        expect(mockLink.getAttribute('aria-current')).toBe('page');
        expect(mockLink.classList.contains('active')).toBe(true);
    });

    test('active class is set for parent categories via window.activeNavigationPathIdList', () => {
        // Create a subcategory link (current page)
        const subcategoryLink = document.createElement('a');
        subcategoryLink.classList.add('nav-item-subcategory-1-link');
        subcategoryLink.setAttribute('href', 'https://example.com/subcategory');
        mockElement.appendChild(subcategoryLink);

        // Create a top-level category link (parent in path)
        const topLevelLink = document.createElement('a');
        topLevelLink.classList.add('nav-item-category-a-link');
        topLevelLink.setAttribute('href', 'https://example.com/category-a');
        mockElement.appendChild(topLevelLink);

        // Set the current page as subcategory
        window.activeNavigationId = 'subcategory-1';
        // Set the path to include the parent category
        window.activeNavigationPathIdList = ['category-a'];

        navbarPlugin._setCurrentPage();

        // Subcategory should have aria-current and active class
        expect(subcategoryLink.getAttribute('aria-current')).toBe('page');
        expect(subcategoryLink.classList.contains('active')).toBe(true);

        // Top-level category should have active class (but no aria-current)
        expect(topLevelLink.getAttribute('aria-current')).toBeNull();
        expect(topLevelLink.classList.contains('active')).toBe(true);
    });

    test('active class is set for parent categories via options.pathIdList as fallback', () => {
        // Create a top-level category link (parent in path)
        const topLevelLink = document.createElement('a');
        topLevelLink.classList.add('nav-item-category-b-link');
        topLevelLink.setAttribute('href', 'https://example.com/category-b');
        mockElement.appendChild(topLevelLink);

        // Clear window.activeNavigationPathIdList to test fallback
        delete window.activeNavigationPathIdList;
        window.activeNavigationId = 'subcategory-2';

        // Create a new plugin instance with pathIdList option
        const pluginWithOptions = new NavbarPlugin(mockElement, {
            pathIdList: ['category-b'],
        }, false);
        pluginWithOptions._topLevelLinks = [mockLink];
        pluginWithOptions._setCurrentPage();

        // Top-level category should have active class
        expect(topLevelLink.classList.contains('active')).toBe(true);
    });

    describe('active path resolution', () => {
        /**
         * Builds a navbar with one top level item, whose dropdown contains the given flyout category.
         */
        function createNavbar(topLevelId, flyoutId) {
            const navbar = document.createElement('nav');
            navbar.innerHTML = `
                <ul>
                    <li class="nav-item nav-item-${topLevelId} dropdown">
                        <a class="nav-link nav-item-${topLevelId}-link main-navigation-link dropdown-toggle" href="#">Top</a>
                        <div class="dropdown-menu">
                            <a class="nav-item nav-item-${flyoutId} nav-link nav-item-${flyoutId}-link navigation-flyout-link" href="#">Flyout</a>
                        </div>
                    </li>
                </ul>
            `;

            return navbar;
        }

        function createPlugin(navbar, options = {}) {
            const plugin = new NavbarPlugin(navbar, options, false);
            plugin._topLevelLinks = navbar.querySelectorAll('.main-navigation-link');

            return plugin;
        }

        afterEach(() => {
            delete window.activeNavigationId;
            delete window.activeNavigationPathIdList;
        });

        test('window.activeNavigationPathIdList wins over the pathIdList option', () => {
            const navbar = createNavbar('top', 'flyout');
            const otherLink = document.createElement('a');
            otherLink.classList.add('nav-item-other-link');
            navbar.appendChild(otherLink);

            window.activeNavigationId = 'flyout';
            window.activeNavigationPathIdList = ['top'];

            createPlugin(navbar, { pathIdList: ['other'] })._setCurrentPage();

            expect(navbar.querySelector('.nav-item-top-link').classList.contains('active')).toBe(true);
            expect(otherLink.classList.contains('active')).toBe(false);
        });

        test('an empty window.activeNavigationPathIdList disables the fallbacks', () => {
            const navbar = createNavbar('top', 'flyout');

            window.activeNavigationId = 'flyout';
            window.activeNavigationPathIdList = [];

            createPlugin(navbar, { pathIdList: ['top'] })._setCurrentPage();

            expect(navbar.querySelector('.nav-item-top-link').classList.contains('active')).toBe(false);
        });

        test('the top level link containing the current page is derived from the DOM without any path list', () => {
            const navbar = createNavbar('top', 'flyout');

            window.activeNavigationId = 'flyout';

            createPlugin(navbar)._setCurrentPage();

            const flyoutLink = navbar.querySelector('.nav-item-flyout-link');
            const topLevelLink = navbar.querySelector('.nav-item-top-link');

            expect(flyoutLink.classList.contains('active')).toBe(true);
            expect(flyoutLink.getAttribute('aria-current')).toBe('page');
            expect(topLevelLink.classList.contains('active')).toBe(true);
            expect(topLevelLink.getAttribute('aria-current')).toBeNull();
        });

        test('nothing is marked active when the current page is not part of the navbar', () => {
            const navbar = createNavbar('top', 'flyout');

            window.activeNavigationId = 'deep-category';

            createPlugin(navbar)._setCurrentPage();

            expect(navbar.querySelectorAll('.active')).toHaveLength(0);
        });

        test('a pathIdList option that is not an array does not throw', () => {
            const navbar = createNavbar('top', 'flyout');

            window.activeNavigationId = 'flyout';

            const plugin = createPlugin(navbar, { pathIdList: 'top' });

            expect(() => plugin._setCurrentPage()).not.toThrow();
            expect(navbar.querySelector('.nav-item-top-link').classList.contains('active')).toBe(true);
        });

        test('the pathIdList option keeps its default for plugins extending NavbarPlugin', () => {
            class CustomNavbarPlugin extends NavbarPlugin {
                _setCurrentPage() {
                    this.options.pathIdList.forEach(() => {});
                }
            }

            const navbar = createNavbar('top', 'flyout');
            const plugin = new CustomNavbarPlugin(navbar, {}, false);

            expect(plugin.options.pathIdList).toEqual([]);
            expect(() => plugin._setCurrentPage()).not.toThrow();
        });
    });

    test('_restoreFocusAfterBtnClose should focus related dropdown top level link', () => {
        window.focusHandler = new FocusHandler();

        const mockNavItem = document.createElement('div');
        mockNavItem.classList.add('nav-item');
        const mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.focus = jest.fn();
        mockNavItem.appendChild(mockLink);
        const mockDropdown = document.createElement('div');
        mockNavItem.appendChild(mockDropdown);

        const mockEvent = {
            target: mockDropdown,
            relatedTarget: null,
        };

        navbarPlugin._restoreFocusAfterBtnClose(mockEvent);

        expect(mockLink.focus).toHaveBeenCalled();
    });

    test('_restoreFocusAfterBtnClose should skip events dispatched for top level links', () => {
        window.focusHandler = new FocusHandler();

        const mockNavItem = document.createElement('div');
        mockNavItem.classList.add('nav-item');
        const mockLink = document.createElement('a');
        mockLink.classList.add('main-navigation-link');
        mockLink.focus = jest.fn();
        mockNavItem.appendChild(mockLink);

        const mockEvent = {
            target: mockLink,
            relatedTarget: null,
        };

        navbarPlugin._restoreFocusAfterBtnClose(mockEvent);

        expect(mockLink.focus).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should close all dropdowns on mouseenter nav item without dropdown', () => {
        jest.useFakeTimers();

        const mockEventEnter = {type: 'mouseenter'};
        const mockLinkNoDropdown = mockLink.cloneNode();
        mockLinkNoDropdown.noDropdown = true;
        navbarPlugin._topLevelLinks.push(mockLinkNoDropdown);
        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValueOnce(false).mockReturnValueOnce(false).mockReturnValueOnce(true)}},
        };
        const mockNoDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: null, // simulate no dropdown menu
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: (link) => link.noDropdown ? mockNoDropdown : mockDropdown,
                getInstance: (link) => link.noDropdown ? mockNoDropdown : mockDropdown,
            },
        };
        jest.spyOn(navbarPlugin, '_closeAllDropdowns');

        // toggle link with dropdown
        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        jest.runAllTimers();

        expect(navbarPlugin._closeAllDropdowns).toHaveBeenCalled();
        expect(mockDropdown.show).toHaveBeenCalled();

        // reset _closeAllDropdowns call count
        navbarPlugin._closeAllDropdowns.mockClear();

        // toggle link without dropdown
        navbarPlugin._toggleNavbar(mockLinkNoDropdown, mockEventEnter);

        jest.runAllTimers();

        expect(navbarPlugin._closeAllDropdowns).toHaveBeenCalled();
        expect(mockDropdown.hide).toHaveBeenCalled();
        expect(mockNoDropdown.show).not.toHaveBeenCalled();
    });

    test('_toggleNavbar should blur top level link on mouseenter', () => {
        jest.useFakeTimers();

        const mockEventEnter = {type: 'mouseenter'};
        const mockDropdown = {
            show: jest.fn(),
            hide: jest.fn(),
            _menu: {classList: {contains: jest.fn().mockReturnValue(false)}},
        };
        window.bootstrap = {
            Dropdown: {
                getOrCreateInstance: jest.fn().mockReturnValue(mockDropdown),
                getInstance: jest.fn().mockReturnValue(mockDropdown),
            },
        };
        mockLink.blur = jest.fn();

        navbarPlugin._toggleNavbar(mockLink, mockEventEnter);

        jest.runAllTimers();

        expect(mockLink.blur).toHaveBeenCalled();
    });
});
