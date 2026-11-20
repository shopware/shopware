/**
 * @sw-package framework
 *
 * All-or-nothing conversions of the upgrade codemods: the conversion calls `fail(reason)` as soon as one part
 * can't be rewritten, and `attempt()` returns that reason for the TODO comment instead of a partial result.
 */

class Unconvertible extends Error {}

function fail(reason) {
    throw new Unconvertible(reason);
}

function attempt(convert) {
    try {
        return { result: convert(), reason: null };
    } catch (error) {
        if (!(error instanceof Unconvertible)) {
            throw error;
        }

        return { result: null, reason: error.message };
    }
}

module.exports = {
    fail,
    attempt,
};
