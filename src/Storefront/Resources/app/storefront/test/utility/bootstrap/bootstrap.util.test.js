import Feature from 'src/helper/feature.helper';

/**
 * @package storefront
 */
describe('BootstrapUtil tests', () => {
    let BootstrapUtil;

    beforeAll(async () => {
        global.bootstrap = {
            Tooltip: jest.fn(),
            Popover: jest.fn(),
        }

        /** @deprecated tag:v6.5.0 - Remove feature init */
        Feature.init({ 'v6.5.0.0': true });

        /**
         * @deprecated tag:v6.5.0 - Use synchronous import instead.
         * Using async import for now because imported module uses feature flag check in constants.
         */
        const module = await import('src/utility/bootstrap/bootstrap.util');
        BootstrapUtil = module.default;

        document.body.innerHTML = `
            <button class="btn" data-bs-toggle="tooltip" title="Tooltip text">
                Button with Tooltip
            </button>

            <button class="btn" data-bs-toggle="popover" title="Tooltip text">
                Button with Popover
            </button>
        `
    });

    test('initializes all Bootstrap plugins', () => {
        BootstrapUtil.initBootstrapPlugins();

        expect(bootstrap.Tooltip).toHaveBeenCalledTimes(1);
        expect(bootstrap.Popover).toHaveBeenCalledTimes(1);
    });

    test('initializes Bootstrap tooltip', () => {
        BootstrapUtil.initTooltip();

        expect(bootstrap.Tooltip).toHaveBeenCalledTimes(1);
        expect(bootstrap.Popover).toHaveBeenCalledTimes(0);
    });

    test('initializes Bootstrap popover', () => {
        BootstrapUtil.initPopover();

        expect(bootstrap.Tooltip).toHaveBeenCalledTimes(0);
        expect(bootstrap.Popover).toHaveBeenCalledTimes(1);
    });
});

/** @deprecated tag:v6.5.0 - Remove all tests in describe block */
describe('BootstrapUtil tests with Bootstrap 4 and jQuery', () => {
    let BootstrapUtil;

    beforeAll(async () => {
        Feature.init({ 'v6.5.0.0': false });

        // Use async import because imported module uses feature flag check in constants
        const module = await import('src/utility/bootstrap/bootstrap.util');
        BootstrapUtil = module.default;

        document.body.innerHTML = `
            <button class="btn" data-toggle="tooltip" title="Tooltip text">
                Button with Tooltip
            </button>

            <button class="btn" data-toggle="popover" title="Tooltip text">
                Button with Popover
            </button>
        `
    });

    test('initializes all Bootstrap plugins', () => {
        BootstrapUtil.initBootstrapPlugins();

        expect($.fn.tooltip).toHaveBeenCalledTimes(1);
        expect($.fn.popover).toHaveBeenCalledTimes(1);
    });

    test('initializes Bootstrap tooltip', () => {
        BootstrapUtil.initTooltip();

        expect($.fn.tooltip).toHaveBeenCalledTimes(1);
        expect($.fn.popover).toHaveBeenCalledTimes(0);
    });

    test('initializes Bootstrap popover', () => {
        BootstrapUtil.initPopover();

        expect($.fn.tooltip).toHaveBeenCalledTimes(0);
        expect($.fn.popover).toHaveBeenCalledTimes(1);
    });
});
