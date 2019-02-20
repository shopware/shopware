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
            expect(data).to.be.an('object');
            expect(data.expiry).to.be.a('number');
            expect(data.access).to.be.a('string');
            expect(data.refresh).to.be.a('string');
            done();
        });
    });

    it('should store the bearer authentication object in the localStorage', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const localStorageObject = JSON.parse(localStorage.getItem('bearerAuth'));

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should get the bearer authentication object from the localStorage', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const localStorageObject = loginService.getBearerAuthentication();

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should clear the localStorage entry', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });

        const localStorageObject = JSON.parse(localStorage.getItem('bearerAuth'));

        expect(localStorageObject).to.deep.equal(authObject);

        loginService.logout();
        const clearedObject = localStorage.getItem('bearerAuth');

        expect(clearedObject).to.equal(null);
    });

    it('should provide the bearer token', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const token = loginService.getToken();

        expect(token).to.equal(authObject.access);
    });

    it('should provide the expiry date', () => {
        const authObject = loginService.setBearerAuthentication({
            expiry: 9999999999,
            access: 'foobar',
            refresh: 'barbatz'
        });
        const expiry = loginService.getBearerAuthentication('expiry');

        expect(expiry).to.equal(authObject.expiry);
    });

    itAsync('should refresh the token', (done) => {
        loginService.loginByUsername('admin', 'shopware').then((data) => {
            loginService.refreshToken().then((accessToken) => {
                expect(accessToken).to.not.equal(data.access);
                done();
            });
        });
    });

    it('should call the logout listener', () => {
        let wasCalled = false;

        loginService.addOnLogoutListener(() => {
            wasCalled = true;
        });
        loginService.logout();

        expect(wasCalled).to.equal(true);
    });

    itAsync('should call the token changed listener on login', (done) => {
        let wasCalled = false;
        let auth = null;

        loginService.addOnTokenChangedListener((data) => {
            wasCalled = true;
            auth = data;
        });
        loginService.loginByUsername('admin', 'shopware').then((data) => {
            expect(wasCalled).to.equal(true);
            expect(auth).to.equal(data);
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
                expect(wasCalled).to.equal(true);
                expect(auth.access).to.equal(accessToken);
                done();
            });
        });
    });

    itAsync('should be logged in after login', (done) => {
        loginService.loginByUsername('admin', 'shopware').then(() => {
            const loggedIn = loginService.isLoggedIn();

            expect(loggedIn).to.equal(true);
            done();
        });
    });
});
