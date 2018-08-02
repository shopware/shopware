export function beforeAsync(fn, timeout = 1000) {
    before(function asyncBeforeHook(done) {
        const context = this;
        context.timeout(timeout);
        return fn.call(context, done);
    });
}

export function beforeEachAsync(fn, timeout = 1000) {
    beforeEach(function asyncBeforeEachHook(done) {
        const context = this;
        context.timeout(timeout);
        return fn.call(context, done);
    });
}

export function afterEachAsync(fn, timeout = 1000) {
    afterEach(function asyncAfterEachHook(done) {
        const context = this;
        context.timeout(timeout);
        return fn.call(context, done);
    });
}

export function itAsync(title, fn, timeout = 10000) {
    it(title, function asyncItHook(done) {
        const context = this;
        context.timeout(timeout);
        return fn.call(context, done);
    });
}

export function xitAsync(title, fn, timeout = 10000) {
    xit(title, function asyncXitHook(done) {
        const context = this;
        context.timeout(timeout);
        return fn.call(context, done);
    });
}

export default {
    beforeAsync,
    beforeEachAsync,
    afterEachAsync,
    itAsync,
    xitAsync
};
