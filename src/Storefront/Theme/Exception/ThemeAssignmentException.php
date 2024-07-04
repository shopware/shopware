<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class ThemeAssignmentException extends ShopwareHttpException
{
    /**
     * @param array<string, array<int, string>> $themeSalesChannel
     * @param array<string, array<int, string>> $childThemeSalesChannel
     * @param array<string, string> $assignedSalesChannels
     */
    public function __construct(
        string $themeName,
        array $themeSalesChannel,
        array $childThemeSalesChannel,
        private readonly array $assignedSalesChannels,
        ?\Throwable $e = null
    ) {
        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and sales channel assignments: {{ assignments }}.';
        $assignments = '';
        if (\count($themeSalesChannel) > 0) {
            $assignments .= $this->formatAssignments($themeSalesChannel);
        }

        if (\count($childThemeSalesChannel) > 0) {
            $assignments .= $this->formatAssignments($childThemeSalesChannel);
        }
        $parameters['assignments'] = $assignments;

        parent::__construct($message, $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'THEME__THEME_ASSIGNMENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    /**
     * @return array<string, string>|null
     */
    public function getAssignedSalesChannels(): ?array
    {
        return $this->assignedSalesChannels;
    }

    /**
     * @param array<string, array<int, string>> $assignmentMapping
     */
    private function formatAssignments(array $assignmentMapping): string
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelIds) {
            $salesChannelNames = [];
            foreach ($salesChannelIds as $salesChannelId) {
                if ($this->assignedSalesChannels[$salesChannelId]) {
                    $salesChannel = $this->assignedSalesChannels[$salesChannelId];
                } else {
                    $salesChannelNames[] = $salesChannelId;

                    continue;
                }

                $salesChannelNames[] = $salesChannel;
            }

            $output[] = sprintf('"%s" => "%s"', $themeName, implode(', ', $salesChannelNames));
        }

        return implode(', ', $output);
    }
}
