import AppClientService from '../../src/service/app-client.service';

/**
 * @package storefront
 */
describe('App Client Service', () => {
    beforeEach(() => {
        window['router']['frontend.app-system.generate-token'] = 'http://localhost/Placeholder';
        window.sessionStorage.clear();
    });

    it('handles not customer logged in', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: false,
                status: 403,
                text: () => Promise.resolve('Error'),
            })
        );

        const appClientService = new AppClientService('test');

        await expect(appClientService.get('https://my-app-backend.com')).rejects.toThrow('Error while fetching token');
    });

    it('passes token to follow up', async () => {
        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockImplementationOnce((url, args) => {
                expect(url).toBe('https://my-app-backend.com');
                expect(args.headers['shopware-app-token']).toBe('test-token')
                expect(args.headers['shopware-app-shop-id']).toBe('foo');

                return Promise.resolve({
                    ok: true,
                });
            });

        const appClientService = new AppClientService('test');

        const resp = await appClientService.get('https://my-app-backend.com');
        expect(resp.ok).toBe(true);
    });

    it('token request gets cached', async () => {
        const expires = new Date();
        expires.setTime(expires.getTime() + 3000);

        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: expires.toISOString(),
                }),
            }))
            .mockImplementationOnce((url, args) => {
                expect(url).toBe('https://my-app-backend.com');
                expect(args.headers['shopware-app-token']).toBe('test-token')
                expect(args.headers['shopware-app-shop-id']).toBe('foo');

                return Promise.resolve({
                    ok: true,
                });
            })
            .mockImplementationOnce((url, args) => {
                expect(url).toBe('https://my-app-backend.com');
                expect(args.headers['shopware-app-token']).toBe('test-token')
                expect(args.headers['shopware-app-shop-id']).toBe('foo');

                return Promise.resolve({
                    ok: true,
                });
            });

        const appClientService = new AppClientService('test');

        const resp = await appClientService.get('https://my-app-backend.com');
        expect(resp.ok).toBe(true);

        // The second request should not trigger a token request, so we have 3
        const resp2 = await appClientService.get('https://my-app-backend.com');
        expect(resp2.ok).toBe(true);

        expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('expired token gets again requested', async () => {
        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
            }))
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
            }));

        const appClientService = new AppClientService('test');

        await appClientService.get('https://my-app-backend.com');
        await appClientService.patch('https://my-app-backend.com');

        expect(global.fetch).toHaveBeenCalledTimes(4);
    });

    it('reset', async () => {
        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
            }));

        const appClientService = new AppClientService('test');

        await appClientService.get('https://my-app-backend.com');

        expect(window.sessionStorage.length).toBe(1);
        appClientService.reset();

        expect(window.sessionStorage.length).toBe(0);
    });

    it('test post', async () => {
        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockImplementation((url, args) => {
                expect(args.method).toBe('POST');

                return Promise.resolve({
                    ok: true,
                });
            });

        const appClientService = new AppClientService('test');

        const resp = await appClientService.post('https://my-app-backend.com');
        expect(resp.ok).toBe(true);
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });

    it('test delete', async () => {
        global.fetch = jest.fn()
            .mockReturnValueOnce(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    token: 'test-token',
                    shopId: 'foo',
                    expires: 0,
                }),
            }))
            .mockImplementation((url, args) => {
                expect(args.method).toBe('DELETE');

                return Promise.resolve({
                    ok: true,
                });
            });

        const appClientService = new AppClientService('test');

        const resp = await appClientService.delete('https://my-app-backend.com');
        expect(resp.ok).toBe(true);
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });
})
