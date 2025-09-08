<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate as MailData;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class Migration1757057005MailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1757057005;
    }

    public function update(Connection $connection): void
    {
        $mailUpdateData = $this->getMailData();

        $this->updateMail($mailUpdateData, $connection);
    }

    private function getMailData(): MailData
    {
        $filesystem = new Filesystem();

        return new MailData(
            'admin_sso_user_invite',
            $filesystem->readFile(__DIR__ . '/assets/sso_user_invitation_mail.en-GB.txt'),
            $filesystem->readFile(__DIR__ . '/assets/sso_user_invitation_mail.en-GB.html.twig'),
            $filesystem->readFile(__DIR__ . '/assets/sso_user_invitation_mail.de-DE.txt'),
            $filesystem->readFile(__DIR__ . '/assets/sso_user_invitation_mail.de-DE.html.twig'),
        );
    }
}
