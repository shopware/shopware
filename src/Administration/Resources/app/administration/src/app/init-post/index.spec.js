import initPost from 'src/app/init-post/index';

describe('src/app/init-post/index.ts', () => {
    it('should export all post initializer', () => {
        expect(initPost).toEqual({
            cookies: expect.any(Function),
            language: expect.any(Function),
            userInformation: expect.any(Function),
            worker: expect.any(Function),
        });
    });
});
