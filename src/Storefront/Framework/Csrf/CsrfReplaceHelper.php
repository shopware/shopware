<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfReplaceHelper
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var bool
     */
    private $csrfEnabled;

    /**
     * @var string
     */
    private $csrfMode;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled, string $csrfMode)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
    }

    public function replaceCsrfToken(Response $response)
    {
        if (!$this->csrfEnabled || $this->csrfMode !== CsrfModes::MODE_TWIG) {
            return $response;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return $response;
        }

        if (!preg_match('/^text\/html/', $response->headers->get('Content-Type'))) {
            return $response;
        }

        $content = $response->getContent();

        $replacements = [];
        $patterns = [];
        $matches = [];
        if (preg_match_all('/<!-- csrf\.(.*) (.*) -->/', $content, $matches)) {
            foreach ($matches[1] as $key => $intent) {
                $replacements[] = sprintf(
                    '<input type="hidden" name="_csrf_token" value="%s" %s>',
                    $this->csrfTokenManager->getToken($intent),
                    $matches[2][$key]
                );

                $patterns[] = sprintf('/<!-- csrf\.%s .* -->/', preg_quote($intent));
            }

            $content = preg_replace($patterns, $replacements, $content);
            $response->setContent($content);
        }

        return $response;
    }
}
