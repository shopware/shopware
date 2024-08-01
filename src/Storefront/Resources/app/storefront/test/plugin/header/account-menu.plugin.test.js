import OffCanvasAccountMenu from 'src/plugin/header/account-menu.plugin';

describe('OffCanvasAccountMenuPlugin tests', () => {
    let plugin;

    beforeEach(() => {
        document.body.innerHTML = `
        <div class="account-menu">
            <div class="dropdown">
                <button class="btn account-menu-btn header-actions-btn show" type="button" id="accountWidget" data-offcanvas-account-menu="true" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true" aria-label="Your account" title="Your account">
                    <span class="icon icon-avatar"></span>
                </button>

                <div class="dropdown-menu dropdown-menu-right account-menu-dropdown js-account-menu-dropdown show" aria-labelledby="accountWidget" data-popper-placement="bottom-end">
                    <div class="offcanvas-header p-0">
                        <button class="btn btn-light offcanvas-close js-offcanvas-close">
                            <span class="icon icon-x icon-sm"></span>
                            Close menu
                        </button>
                    </div>

                    <div class="offcanvas-body">
                        <div class="account-menu">
                            <div class="dropdown-header account-menu-header">Your account</div>

                            <div class="account-menu-login">
                                <a href="/account/login" title="Log in" class="btn btn-primary account-menu-login-button">Log in</a>
                                <div class="account-menu-register">or <a href="/account/login" title="Sign up">sign up</a></div>
                            </div>

                            <div class="account-menu-links">
                                <div class="header-account-menu">
                                    <div class="card account-menu-inner">
                                        <div class="list-group list-group-flush account-aside-list-group">
                                            <a href="/account" title="Overview" class="list-group-item list-group-item-action account-aside-item">Overview</a>
                                            <a href="/account/profile" title="Your profile" class="list-group-item list-group-item-action account-aside-item">Your profile</a>
                                            <a href="/account/address" title="Addresses" class="list-group-item list-group-item-action account-aside-item">Addresses</a>
                                            <a href="/account/payment" title="Payment methods" class="list-group-item list-group-item-action account-aside-item">Payment methods</a>
                                            <a href="/account/order" title="Orders" class="list-group-item list-group-item-action account-aside-item">Orders</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;

        const el = document.querySelector('[data-offcanvas-account-menu]');

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        plugin = new OffCanvasAccountMenu(el);

        // Simulate mobile viewport
        plugin._isInAllowedViewports = () => true;

        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('Creates plugin instance', () => {
        expect(typeof plugin).toBe('object');
    });

    test('Opens an OffCanvas with the account menu HTML', () => {
        // Click on trigger element
        plugin.el.dispatchEvent(new Event('click'));

        jest.runAllTimers();

        // Ensure OffCanvas exists with account menu HTML
        expect(document.querySelector('.offcanvas.account-menu-offcanvas .account-menu-inner')).toBeTruthy();
    });
});
