/**
 * @sw-package checkout
 */

// Re-exported from core so nothing in core has to reach into this module for it, and so an
// extension that already imports this path keeps working.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { default } from 'src/core/constant/customer.constant';
