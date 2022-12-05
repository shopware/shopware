import BootstrapUtil from 'src/utility/bootstrap/bootstrap.util';

/**
 * @package storefront
 */
describe('BootstrapUtil tests', () => {
    beforeAll(() => {
        global.bootstrap = {
            Tooltip: jest.fn(),
            Popover: jest.fn(),
        }

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
