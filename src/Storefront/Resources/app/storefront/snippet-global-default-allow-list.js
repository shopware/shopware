/**
 * @sw-package discovery
 *
 * Snippet keys that are allowed to keep a translation identical to a `global.default` entry in
 * every locale. Take the key from the `sw-core-rules/require-global-default-use` error and add it
 * here — with a comment explaining why the duplicate is intentional.
 *
 * An entry matches either a single key or a whole namespace: `account` covers
 * `account.profile.successLabel` and everything else below it.
 */
module.exports = [];
