import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';

describe('plugin/google-analytics/checkout-step.helper', () => {
    beforeEach(() => {
        window.sessionStorage.clear();
    });

    test('reports a step as not sent until it is marked', () => {
        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);

        CheckoutStepHelper.markReported('add_shipping_info');

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(true);
    });

    test('keeps steps independent of each other', () => {
        CheckoutStepHelper.markReported('add_shipping_info');

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(true);
        expect(CheckoutStepHelper.hasReported('add_payment_info')).toBe(false);
    });

    test('marking the same step twice does not duplicate it', () => {
        CheckoutStepHelper.markReported('add_payment_info');
        CheckoutStepHelper.markReported('add_payment_info');

        expect(JSON.parse(window.sessionStorage.getItem('swGaReportedCheckoutSteps'))).toEqual(['add_payment_info']);
    });

    test('reset clears every reported step', () => {
        CheckoutStepHelper.markReported('add_shipping_info');
        CheckoutStepHelper.markReported('add_payment_info');

        CheckoutStepHelper.reset();

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);
        expect(CheckoutStepHelper.hasReported('add_payment_info')).toBe(false);
    });

    test('survives a corrupted storage value', () => {
        window.sessionStorage.setItem('swGaReportedCheckoutSteps', 'not-json');

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);
    });

    test('ignores a stored value that is not a list', () => {
        window.sessionStorage.setItem('swGaReportedCheckoutSteps', '{"add_shipping_info":true}');

        expect(CheckoutStepHelper.hasReported('add_shipping_info')).toBe(false);
    });
});
