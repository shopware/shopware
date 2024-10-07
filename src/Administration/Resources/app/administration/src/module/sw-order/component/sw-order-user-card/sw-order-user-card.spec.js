/**
 * @package customer-order
 */

describe('modules/sw-order/component/sw-order-user-card/tracking-code-display', () => {
    let userCard;

    beforeAll(async () => {
        userCard = await wrapTestComponent('sw-order-user-card', {
            sync: true,
        });
    });

    const trackingCode = 'TR-4CK1N-GCD';
    const reservedCharacters = ';,/?:@&=+$';
    const unescapedCharacters = "-_.!~*'()";
    const spaceSeparatedWords = 'lorem ipsum dolor sit amet';

    const emptyTrackingUrl = '';
    const trackingUrl = 'https://tracking.example.com?lang=de&shipment=';
    const trackingUrlWithPlaceholder = `${trackingUrl}%s`;

    const shippingMethodNoUrl = { trackingUrl: emptyTrackingUrl };
    const shippingMethodWithoutPlaceholder = { trackingUrl: trackingUrl };
    const shippingMethodWithPlaceholder = {
        trackingUrl: trackingUrlWithPlaceholder,
    };

    it('should render no url, when no base url is present in the shipping method', async () => {
        expect(userCard.methods.renderTrackingUrl(trackingCode, shippingMethodNoUrl)).toBe('');
    });

    it('should render the same url, when no placeholder is present', async () => {
        expect(userCard.methods.renderTrackingUrl(trackingCode, shippingMethodWithoutPlaceholder)).toBe(trackingUrl);
    });

    it('should render an intact tracking url with an innocuous tracking code', async () => {
        expect(userCard.methods.renderTrackingUrl(trackingCode, shippingMethodWithPlaceholder)).toBe(
            `${trackingUrl}${trackingCode}`,
        );
    });

    it('should render an intact tracking url with a tracking code containing reserved characters', async () => {
        expect(
            userCard.methods.renderTrackingUrl(`${trackingCode}${reservedCharacters}`, shippingMethodWithPlaceholder),
        ).toBe(`${trackingUrl}${trackingCode}%3B%2C%2F%3F%3A%40%26%3D%2B%24`);
    });

    it(
        'should render an intact tracking url ' +
            "with a tracking code containing special characters which don't need escaping",
        async () => {
            expect(
                userCard.methods.renderTrackingUrl(`${trackingCode}${unescapedCharacters}`, shippingMethodWithPlaceholder),
            ).toBe(`${trackingUrl}${trackingCode}-_.!~*'()`);
        },
    );

    it('should render an intact tracking url with a tracking code containing spaces', async () => {
        expect(
            userCard.methods.renderTrackingUrl(`${trackingCode}${spaceSeparatedWords}`, shippingMethodWithPlaceholder),
        ).toBe(`${trackingUrl}${trackingCode}lorem%20ipsum%20dolor%20sit%20amet`);
    });
});
