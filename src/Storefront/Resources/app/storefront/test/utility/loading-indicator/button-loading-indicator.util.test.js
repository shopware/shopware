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
        expect(buttonEl.querySelector('.loader').textContent).toContain('Loading...');
    });

    test('adds a loading indicator to anchor element', () => {
        const buttonEl = document.querySelector('a.anchor-el');
        const buttonLoadingIndicatorUtil = new ButtonLoadingIndicatorUtil(buttonEl);
        buttonLoadingIndicatorUtil.create();

        // Verify element renders loader and sets disabled class instead of attribute
        expect(buttonEl.disabled).toBeUndefined();
        expect(buttonEl.classList.contains('disabled')).toBe(true);
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
        expect(buttonEl.querySelector('.loader')).toBeNull();
    });

    test('throws an error if parent element is not of type button or anchor', () => {
        expect(() => {
            new ButtonLoadingIndicatorUtil(document.querySelector('.illegal-button'));
        }).toThrow('Parent element is not of type <button> or <a>');
    });
});
