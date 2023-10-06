/**
 * @package admin
 *
 * You can activate acl roles in the each test like this:
 * global.activeAclRoles = ['product.editor'];
 */

global.activeAclRoles = [];

const aclService = {
    can: (key) => {
        if (!key) { return true; }

        return global.activeAclRoles.includes(key);
    },
};

export default aclService;
