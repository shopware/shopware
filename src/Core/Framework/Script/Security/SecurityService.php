<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Security;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @script-service custom_endpoint
 */
#[Package('core')]
class SecurityService
{
    private SecurityOptions $securityOptions;

    /**
     * @internal
     */
    public function __construct(
        private readonly ?string $nonce
    ) {
        $this->securityOptions = new SecurityOptions();
    }

    /**
     * Returns nonce value from the content security policy.
     *
     * @return string A nonce
     */
    public function nonce(): string
    {
        return $this->nonce;
    }

    /**
     * Change the content security policy. Use the response function in this service and not the response service.
     *
     * @param string|null $contentSecurityPolicy The new content security policy
     */
    public function setContentSecurityPolicy(?string $contentSecurityPolicy): void
    {
        $this->securityOptions->setOption(SecurityOptions::CSP_HEADER, $contentSecurityPolicy);
    }

    /**
     * Change the frame options. Use the response function in this service and not the response service.
     *
     * @param string|null $frameOptions The new frame options
     */
    public function setFrameOptions(?string $frameOptions): void
    {
        $this->securityOptions->setOption(SecurityOptions::FRAME_OPTIONS_HEADER, $frameOptions);
    }

    /**
     * Generates a response with current security options.
     *
     * @param string $content HTML content
     * @param int $code HTTP status code
     *
     * @return ScriptResponse A script response
     */
    public function response(string $content, int $code = Response::HTTP_OK): ScriptResponse
    {
        return new ScriptResponse(
            new Response($content, $code, $this->securityOptions->getOptions()),
            $code
        );
    }
}
