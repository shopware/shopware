/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.mail-template-preview-footer',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Mail template preview modal footer Twig blocks are deprecated because the modal footer was removed.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#administration'),
        upgradeGuide('UPGRADE-6.8.md#mail-template-preview-component-changes'),
    ],
    // sw-mail-template-preview-modal defines blocks of the same names, which stay
    usage: removeBlocks(
        'sw-mail-template-detail',
        [
            'sw_mail_template_detail_preview_modal_footer',
            'sw_mail_template_detail_preview_modal_footer_cancel',
        ],
        { message: 'Override the block of the same name in "sw-mail-template-preview-modal" instead.' },
    ),
});
