<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\Response;

class ThemeAssignmentException extends ShopwareHttpException
{
    private array $stillAssignedSalesChannels;

    /**
     * @deprecated tag:v6.5.0 parameter $stillAssignedSalesChannels will be required
     */
    public function __construct(
        string $themeName,
        array $themeSalesChannel,
        array $childThemeSalesChannel,
        ?array $stillAssignedSalesChannels = null,
        ?\Throwable $e = null
    ) {
        $this->stillAssignedSalesChannels = $stillAssignedSalesChannels ?? [];

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
     * @deprecated tag:v6.5.0 - will be removed on v6.5.0 use `getAssignedSalesChannels` instead
     */
    public function getStillAssignedSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection();
    }

    public function getAssignedSalesChannels(): ?array
    {
        return $this->stillAssignedSalesChannels;
    }

    private function formatAssignments(array $assignmentMapping): string
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelIds) {
            $salesChannelNames = [];
            foreach ($salesChannelIds as $salesChannelId) {
                if ($this->stillAssignedSalesChannels[$salesChannelId]) {
                    $salesChannel = $this->stillAssignedSalesChannels[$salesChannelId];
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
