import QuantitySelectorPlugin from 'src/plugin/quantity-selector/quantity-selector.plugin';
import OffCanvasCartPlugin from 'src/plugin/offcanvas-cart/offcanvas-cart.plugin';
import FormAutoSubmitPlugin from 'src/plugin/forms/form-auto-submit.plugin';

/**
 * @package checkout
 */
describe('Quantity selector form submission', () => {
    function renderQuantity(quantity = 1) {
        document.body.innerHTML = `
            <div class="header-cart"></div>
            <div class="js-cart-item">
                <form action="/quantity" method="post">
                    <div data-quantity-selector>
                        <button type="button" class="js-btn-minus">-</button>
                        <input type="number" name="quantity" min="1" max="100" step="1" value="${quantity}"
                            class="js-quantity-selector js-offcanvas-cart-change-quantity-number">
                        <button type="button" class="js-btn-plus">+</button>
                    </div>
                </form>
            </div>`;

        new QuantitySelectorPlugin(document.querySelector('[data-quantity-selector]'), {
            submitOnFinish: true,
            ariaLiveUpdates: false,
        });

        return document.querySelector('input');
    }

    function finishEdit(input, value, finish = 'enter') {
        input.focus();
        input.value = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (finish === 'blur') {
            input.blur();
        } else {
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        }
    }

    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test.each(['enter', 'blur'])('keeps redirect parameters and cancels delayed native updates on %s', (finish) => {
        const input = renderQuantity();
        const form = input.form;
        const autoSubmit = new FormAutoSubmitPlugin(form, { autoFocus: false, delayChangeEvent: 800 });
        jest.spyOn(autoSubmit, '_getLocationSearch').mockReturnValue('?test-param=retained');
        const submissions = [];
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submissions.push(new FormData(form));
        });

        document.querySelector('.js-btn-plus').click();
        finishEdit(input, '3', finish);

        expect(submissions).toHaveLength(1);
        expect(submissions[0].get('redirectParameters[test-param]')).toBe('retained');
        expect(submissions[0].get('quantity')).toBe('3');
        jest.advanceTimersByTime(800);
        expect(submissions).toHaveLength(1);
    });

    test.each(['enter', 'blur'])('keeps AJAX submission and cancels delayed updates on %s', (finish) => {
        const input = renderQuantity();
        const autoSubmit = new FormAutoSubmitPlugin(input.form, {
            autoFocus: false,
            delayChangeEvent: 800,
            useAjax: true,
            ajaxContainerSelector: '.js-cart-item',
        });
        const ajaxSubmit = jest.spyOn(autoSubmit, 'sendAjaxFormSubmit').mockImplementation(() => {});
        const nativeSubmit = jest.fn(event => event.preventDefault());
        input.form.addEventListener('submit', nativeSubmit);

        document.querySelector('.js-btn-plus').click();
        finishEdit(input, '3', finish);

        expect(ajaxSubmit).toHaveBeenCalledTimes(1);
        expect(nativeSubmit).not.toHaveBeenCalled();
        jest.advanceTimersByTime(800);
        expect(ajaxSubmit).toHaveBeenCalledTimes(1);
    });

    test.each(['enter', 'blur'])('does not overwrite a newer offcanvas quantity after %s', (finish) => {
        let input = renderQuantity();
        const cart = new OffCanvasCartPlugin(document.querySelector('.header-cart'));
        const quantities = [];
        jest.spyOn(cart, '_fireRequest').mockImplementation(form => quantities.push(new FormData(form).get('quantity')));
        cart._registerChangeQuantityProductTriggerEvents();

        document.querySelector('.js-btn-plus').click();
        finishEdit(input, '3', finish);
        expect(quantities).toEqual(['3']);

        // A successful response replaces the form before its old debounce timer expires.
        input = renderQuantity(3);
        cart._registerChangeQuantityProductTriggerEvents();
        finishEdit(input, '4', finish);
        expect(quantities).toEqual(['3', '4']);

        jest.advanceTimersByTime(800);
        expect(quantities).toEqual(['3', '4']);
    });

    test.each(['enter', 'blur'])('does not send a value again that the delayed submission already sent, on %s', (finish) => {
        const input = renderQuantity();
        const form = input.form;
        new FormAutoSubmitPlugin(form, { autoFocus: false, delayChangeEvent: 800 });
        const submissions = [];
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submissions.push(new FormData(form).get('quantity'));
        });

        document.querySelector('.js-btn-plus').click();
        // An arrow key press while the step is still delayed rides along with it.
        input.focus();
        input.value = '3';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        jest.advanceTimersByTime(800);
        expect(submissions).toEqual(['3']);

        finishEdit(input, '3', finish);
        expect(submissions).toEqual(['3']);
    });
});
