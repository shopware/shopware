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

export function itAsync(title, fn, timeout = 15000) {
    it(title, function asyncItHook(done) {
        const context = this;

        function doneWithReporting(err) {
            if (err) {
                const status = parseInt(err.response.status, 10);
                if (status < 200 || status > 300) {
                    done(new Error(JSON.stringify(err.response, null, 1)));
                }
            }

            done(err);
        }

        context.timeout(timeout);
        return fn.call(context, doneWithReporting);
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
