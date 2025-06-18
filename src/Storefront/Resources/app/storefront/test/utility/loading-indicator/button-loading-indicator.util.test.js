import ButtonLoadingIndicatorUtil from 'src/utility/loading-indicator/button-loading-indicator.util';

describe('ButtonLoadingIndicatorUtil tests', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <button class="btn button-el">Default button</button>
            <a class="btn anchor-el" href="#">Anchor button</a>
            <div class="btn illegal-button" role="button">Illegal button</div>
        `;
    });

    test('adds a loading indicator to button element', () => {
        const buttonEl = document.querySelector('button.button-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl);
        buttonLoadingIndicatorUtil.create();

        // Verify element renders loader and sets disabled attribute
        expect(buttonEl.disabled).toBe(true);
        expect(buttonEl.classList.contains('disabled')).toBe(false);
        expect(buttonEl.classList.contains('is-loading-indicator-before')).toBe(true);
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');
    });

    test('adds a loading indicator to anchor element', () => {
        const buttonEl = document.querySelector('a.anchor-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl);
        buttonLoadingIndicatorUtil.create();

        // Verify element renders loader and sets disabled class instead of attribute
        expect(buttonEl.disabled).toBeUndefined();
        expect(buttonEl.classList.contains('disabled')).toBe(true);
        expect(buttonEl.classList.contains('is-loading-indicator-before')).toBe(true);
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');
    });

    test('removes a loading indicator from button element', () => {
        const buttonEl = document.querySelector('button.button-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl);
        buttonLoadingIndicatorUtil.create();
        buttonLoadingIndicatorUtil.remove();

        // Verify element removes loader and removes disabled attribute
        expect(buttonEl.disabled).toBe(false);
        expect(buttonEl.classList.contains('disabled')).toBe(false);
        expect(buttonEl.classList.contains('is-loading-indicator-before')).toBe(false);
        expect(buttonEl.querySelector('.loader')).toBeNull();
    });

    test('removes a loading indicator from anchor element', () => {
        const buttonEl = document.querySelector('a.anchor-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl);
        buttonLoadingIndicatorUtil.create();
        buttonLoadingIndicatorUtil.remove();

        // Verify element removes loader and removes disabled class
        expect(buttonEl.disabled).toBeUndefined();
        expect(buttonEl.classList.contains('disabled')).toBe(false);
        expect(buttonEl.classList.contains('is-loading-indicator-before')).toBe(false);
        expect(buttonEl.querySelector('.loader')).toBeNull();
    });

    test('adds a loading indicator after the text', () => {
        const buttonEl = document.querySelector('button.button-el');

        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl, 'after');
        buttonLoadingIndicatorUtil.create();

        // Verify the loading indicator comes after the button text.
        expect(buttonEl.disabled).toBe(true);
        expect(buttonEl.classList.contains('is-loading-indicator-after')).toBe(true);
        expect(buttonEl.innerHTML).toContain('Default button<div class="loader" role="status">');
    });

    test('replaces the button text with a loading indicator when the "inner" parameter is passed', () => {
        const buttonEl = document.querySelector('button.button-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl, 'inner');

        // Mock getBoundingClientRect to simulate a current button width.
        // When the button text is replaced with the loading indicator,
        // the current button width is applied via inline styling to prevent the button from jumping.
        buttonEl.getBoundingClientRect = jest.fn(() => ({
            width: 81.253,
            height: 40,
        }));

        // By default, the textContent should be the initial text.
        expect(buttonEl.textContent).toBe('Default button');

        buttonLoadingIndicatorUtil.create();

        // With "inner" mode we expect the loading indicator, but the original button text is removed.
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');
        expect(buttonEl.textContent).not.toContain('Default button');

        // Expect classes and disabled attribute to be set.
        expect(buttonEl.disabled).toBe(true);
        expect(buttonEl.classList.contains('is-loading-indicator-inner')).toBe(true);

        // Expect the inline style width to be set.
        expect(buttonEl.style.width).toBe('81.253px');
    });

    test('removes the inner loading indicator and restores the original button text', () => {
        const buttonEl = document.querySelector('button.button-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl, 'inner');

        // By default, the textContent should be the initial text.
        expect(buttonEl.textContent).toBe('Default button');

        buttonLoadingIndicatorUtil.create();

        // Verify the loader is present and text removed.
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');
        expect(buttonEl.textContent).not.toContain('Default button');

        // Remove the loading indicator again
        buttonLoadingIndicatorUtil.remove();

        // Expect original text to be restored.
        expect(buttonEl.textContent).toContain('Default button');

        // Expect disabled state to be removed.
        expect(buttonEl.disabled).toBe(false);
        expect(buttonEl.classList.contains('is-loading-indicator-inner')).toBe(false);

        // Expect the inline style width to be set back to auto.
        expect(buttonEl.style.width).toBe('auto');
    });

    test('shows a console warning if the parent element is not of type button or anchor', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        new ButtonLoadingIndicatorUtil(document.querySelector('.illegal-button'));

        expect(consoleSpy).toHaveBeenCalledWith('[ButtonLoadingIndicatorUtil] Parent element is not of type <button> or <a>. Given element: [object HTMLDivElement]');
        consoleSpy.mockRestore();
    });

    test('shows a console warning when attempting to create loading indicator on illegal element', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        const illegalButton = document.querySelector('.illegal-button');
        const indicator = new ButtonLoadingIndicatorUtil(illegalButton);
        indicator.create();

        expect(consoleSpy).toHaveBeenCalledWith('[ButtonLoadingIndicatorUtil] Unable to create loading indicator. Parent element is not of type <button> or <a>. Given element: [object HTMLDivElement]');
        consoleSpy.mockRestore();
    });

    test('shows a console warning when trying to remove a non-existing loading indicator', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        const buttonEl = document.querySelector('button.button-el');
        const indicator = new ButtonLoadingIndicatorUtil(buttonEl);

        indicator.create();
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');

        indicator.remove();
        expect(buttonEl.querySelector('.loader')).toBe(null);

        // Try to remove it again
        indicator.remove();

        expect(consoleSpy).toHaveBeenCalledWith('[ButtonLoadingIndicatorUtil] Unable to remove loading indicator. No indicator present on given element: [object HTMLButtonElement]');
        consoleSpy.mockRestore();
    });
});
