/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-integration-mcp-allowlist needs from outside its mini-module.
 */
/** @private */
export { computePrivilegeChips, isPrivilegeGranted } from 'src/core/helper/mcp-privilege.helper';
/** @private */
export { acl as aclService, mcpToolService } from 'src/app/service-names';
