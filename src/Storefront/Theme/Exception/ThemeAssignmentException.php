<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ThemeAssignmentException extends ShopwareHttpException
{
    public function __construct(string $themeName, array $themeSalesChannel, array $childThemeSalesChannel)
    {
        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and sales channel assignments: {{ assignments }}.';
        $assignments = '';
        if (count($themeSalesChannel) > 0) {
            $assignments .= $this->formatAssignments($themeSalesChannel);
        }

        if (count($childThemeSalesChannel) > 0) {
            $assignments .= $this->formatAssignments($childThemeSalesChannel);
        }
        $parameters['assignments'] = $assignments;

        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'THEME__THEME_ASSIGNMENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    private function formatAssignments(array $assignmentMapping)
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelName) {
            $output[] = sprintf('"%s" => "%s"', $themeName, implode(', ', $salesChannelName));
        }

        return implode(', ', $output);
    }
}
