<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\Response;

class ThemeAssignmentException extends ShopwareHttpException
{
    private SalesChannelCollection $stillAssignedSalesChannels;

    /**
     * @deprecated tag:v6.5.0 parameter $stillAssignedSalesChannels will be required
     */
    public function __construct(string $themeName, array $themeSalesChannel, array $childThemeSalesChannel, ?SalesChannelCollection $stillAssignedSalesChannels = null)
    {
        $this->stillAssignedSalesChannels = $stillAssignedSalesChannels ?? new SalesChannelCollection();

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

    public function getStillAssignedSalesChannels(): SalesChannelCollection
    {
        return $this->stillAssignedSalesChannels;
    }

    private function formatAssignments(array $assignmentMapping)
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelIds) {
            $salesChannelNames = [];
            foreach ($salesChannelIds as $salesChannelId) {
                $salesChannel = $this->getStillAssignedSalesChannels()->get($salesChannelId);
                if (!$salesChannel) {
                    $salesChannelNames[] = $salesChannelId;

                    continue;
                }

                $salesChannelNames[] = $salesChannel->getName();
            }

            $output[] = sprintf('"%s" => "%s"', $themeName, implode(', ', $salesChannelNames));
        }

        return implode(', ', $output);
    }
}
