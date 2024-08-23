import ScrollUpPlugin from 'src/plugin/scroll-up/scroll-up.plugin';

describe('ScrollUpPlugin', () => {
    let pluginInstance;
    let toggleVisibilitySpy;
    let scrollToTopSpy;
    let focusFirstElementSpy;

    beforeEach(() => {
        document.body.style.height = '2000px';
        document.body.innerHTML = `
            <a href="#main-content" class="skip-to-main-content">Skip to main content</a>
            <header>Header</header>

            <main id="main-content">
                Main content
                <button class="btn">Ignore me</button>
            </main>

            <footer></footer>
            <div class="scroll-up-container" data-scroll-up="true">
                <button class="js-scroll-up-button btn btn-primary" aria-label="Go up">
                    <svg></svg>
                </button>
            </div>
        `;

        window.getComputedStyle = jest.fn(() => {
            return {
                getPropertyValue: jest.fn(() => {
                    return '24px';
                }),
            };
        });

        global.MutationObserver = jest.fn().mockImplementation(() => {
            return {
                observe: jest.fn(),
                disconnect: jest.fn(),
                takeRecords: jest.fn(),
            };
        });

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
            setFocus: jest.fn(),
        };

        window.scrollTo = jest.fn();

        toggleVisibilitySpy = jest.spyOn(ScrollUpPlugin.prototype, '_toggleVisibility');
        scrollToTopSpy = jest.spyOn(ScrollUpPlugin.prototype, '_scrollToTop');
        focusFirstElementSpy = jest.spyOn(ScrollUpPlugin.prototype, '_focusFirstElement');

        jest.useFakeTimers();

        const element = document.querySelector('[data-scroll-up]');
        pluginInstance = new ScrollUpPlugin(element);
    });

    afterEach(() => {
        jest.clearAllTimers();
        jest.useRealTimers();
    });

    test('should be a plugin instance', () => {
        expect(pluginInstance).toBeInstanceOf(ScrollUpPlugin);
    });

    test('should apply visible class after scrolling down beyond the visible position', () => {
        // Initially, no visible class should be there
        expect(document.querySelector('.js-scroll-up-button').classList.contains('is-visible')).toBeFalsy();

        // Simulate scrolling down by 500px
        window.scrollY = 500;
        document.dispatchEvent(new Event('scroll', { bubbles: true }));
        jest.runAllTimers();

        // Expect the visible class to be applied
        expect(toggleVisibilitySpy).toHaveBeenCalled();
        expect(document.querySelector('.js-scroll-up-button').classList.contains('is-visible')).toBeTruthy();
    });

    test('should scroll back to the top of the page and focus the first focusable element', () => {
        // Simulate scrolling down by 700px
        window.scrollY = 700;
        document.dispatchEvent(new Event('scroll', { bubbles: true }));
        jest.runAllTimers();

        const scrollUpButton = document.querySelector('.js-scroll-up-button');
        const firstFocusableElement = document.querySelector('.skip-to-main-content');

        // Click the scroll up button
        scrollUpButton.dispatchEvent(new Event('click', { bubbles: true }));

        // Should scroll to top and focus the first focusable element
        expect(scrollToTopSpy).toHaveBeenCalled();
        expect(focusFirstElementSpy).toHaveBeenCalled();
        expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
        expect(window.focusHandler.setFocus).toHaveBeenCalledWith(firstFocusableElement, { preventScroll: true });
    });
});
