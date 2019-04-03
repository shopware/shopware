import { itAsync } from '../../../async-helper';

let loginService;

describe('core/service/login.service.js', () => {
    beforeEach(() => {
        loginService = Shopware.Application.getContainer('service').loginService;
    });
    afterEach(() => {
        loginService.logout();
    });

    itAsync('should request the token and expiry date from the server', (done) => {
        loginService.loginByUsername('admin', 'shopware').then((data) => {
            expect(typeof data).toBe('object');
            expect(typeof data.expiry).toBe('number');
            expect(typeof data.access).toBe('string');
            expect(typeof data.refresh).toBe('string');
            done();
        });
    });

    test(
        'should store the bearer authentication object in the localStorage',
        () => {
            const authObject = loginService.setBearerAuthentication({
                expiry: 9999999999,
                access: 'foobar',
                refresh: 'barbatz'
            });
            const localStorageObject = JSON.parse(localStorage.getItem('bearerAuth'));

            expect(localStorageObject).toBe(authObject);
        }
    );

    test(
        'should get the bearer authentication object from the localStorage',
        () => {
            const authObject = loginService.setBearerAuthentication({
                expiry: 9999999999,
                access: 'foobar',
                refresh: 'barbatz'
            });
            const localStorageObject = loginService.getBearerAuthentication();

            expect(localStorageObject).toBe(authObject);
        }
    );

    test('should clear the localStorage entry', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });

        const localStorageObject = JSON.parse(localStorage.getItem('bearerAuth'));

        expect(localStorageObject).toBe(authObject);

        loginService.logout();
        const clearedObject = localStorage.getItem('bearerAuth');

        expect(clearedObject).toBe(null);
    });

    test('should provide the bearer token', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const token = loginService.getToken();

        expect(token).toBe(authObject.access);
    });

    test('should provide the expiry date', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const expiry = loginService.getBearerAuthentication('expiry');

        expect(expiry).toBe(authObject.expiry);
    });

    itAsync('should refresh the token', (done) => {
        loginService.loginByUsername('admin', 'shopware').then((data) => {
            loginService.refreshToken().then((accessToken) => {
                expect(accessToken).not.toBe(data.access);
                done();
            });
        });
    });

    test('should call the logout listener', () => {
        let wasCalled = false;

        loginService.addOnLogoutListener(() => {
            wasCalled = true;
        });
        loginService.logout();

        expect(wasCalled).toBe(true);
    });

    itAsync('should call the token changed listener on login', (done) => {
        let wasCalled = false;
        let auth = null;

        loginService.addOnTokenChangedListener((data) => {
            wasCalled = true;
            auth = data;
        });
        loginService.loginByUsername('admin', 'shopware').then((data) => {
            expect(wasCalled).toBe(true);
            expect(auth).toBe(data);
            done();
        });
    });

    itAsync('should call the token changed listener on refresh', (done) => {
        let wasCalled = false;
        let auth = null;

        loginService.loginByUsername('admin', 'shopware').then(() => {
            loginService.addOnTokenChangedListener((data) => {
                wasCalled = true;
                auth = data;
            });

            loginService.refreshToken().then((accessToken) => {
                expect(wasCalled).toBe(true);
                expect(auth.access).toBe(accessToken);
                done();
            });
        });
    });

    itAsync('should be logged in after login', (done) => {
        loginService.loginByUsername('admin', 'shopware').then(() => {
            const loggedIn = loginService.isLoggedIn();

            expect(loggedIn).toBe(true);
            done();
        });
    });
});
