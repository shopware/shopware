<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MailActionController extends AbstractController
{
    /**
     * @var AbstractMailService
     */
    private $mailService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    public function __construct(
        AbstractMailService $mailService,
        StringTemplateRenderer $templateRenderer
    ) {
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/mail-template/send",
     *     summary="Send a mail",
     *     description="Generates a mail from a mail template and sends it to the customer.

Take a look at the `salesChannel` entity for possible values. For example `{{ salesChannel.name }}` can be used.",
     *     operationId="send",
     *     tags={"Admin API", "Mail Operations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                  "recipients",
     *                  "salesChannelId",
     *                  "contentHtml",
     *                  "contentPlain",
     *                  "subject",
     *                  "senderName"
     *             },
     *             @OA\Property(
     *                 property="recipients",
     *                 type="object",
     *                 description="A list of recipients with name and mail address.",
     *                 example={"test1@example.com": "Test user 1", "test2@example.com": "Test user 2"},
     *                 @OA\AdditionalProperties(type="string", description="Name of the recipient.")
     *             ),
     *             @OA\Property(
     *                 property="salesChannelId",
     *                 description="Identifier of the sales channel from which the mail should be send.",
     *                 type="string",
     *                 pattern="^[0-9a-f]{32}$"
     *             ),
     *             @OA\Property(
     *                 property="contentHtml",
     *                 description="The content of the mail in HTML format.",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="contentPlain",
     *                 description="The content of the mail as plain text.",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="subject",
     *                 description="Subject of the mail.",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="senderName",
     *                 description="Name of the sender.",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="senderEmail",
     *                 description="Mail address of the sender. If not set, `core.basicInformation.email` or `core.mailerSettings.senderAddress` will be used from the shop configuration.",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="mediaIds",
     *                 description="List of media identifiers which should be attached to the mail.",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="binAttachments",
     *                 description="A list of binary attachments which should be added to the mail.",
     *                 type="array",
     *                 required={"content", "fileName", "mimeType"},
     *                 @OA\Items(
     *                      type="object",
     *                      @OA\Property(
     *                          property="content",
     *                          description="Binary content of the attachment.",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="fileName",
     *                          description="File name of the attachment.",
     *                          type="string"
     *                      ),
     *                      @OA\Property(
     *                          property="mimeType",
     *                          description="Mime type of the attachment.",
     *                          type="string"
     *                      )
     *                  )
     *             ),
     *             @OA\Property(
     *                 property="recipientsBcc",
     *                 type="object",
     *                 description="A list of recipients with name and mail address to be set in BCC.",
     *                 example={"test1@example.com": "Test user 1", "test2@example.com": "Test user 2"},
     *                 @OA\AdditionalProperties(type="string", description="Name of the recipient.")
     *             ),
     *             @OA\Property(
     *                 property="recipientsCc",
     *                 type="object",
     *                 description="A list of recipients with name and mail address to be set in CC.",
     *                 example={"test1@example.com": "Test user 1", "test2@example.com": "Test user 2"},
     *                 @OA\AdditionalProperties(type="string", description="Name of the recipient.")
     *             ),
     *             @OA\Property(
     *                 property="replyTo",
     *                 type="object",
     *                 description="A list of mail addresses with name and mail address to be set in reply to.",
     *                 example={"test1@example.com": "Test user 1", "test2@example.com": "Test user 2"},
     *                 @OA\AdditionalProperties(type="string", description="Name of the recipient.")
     *             ),
     *             @OA\Property(
     *                 property="returnPath",
     *                 type="object",
     *                 description="A list of mail addresses with name and mail address to be set in return path.",
     *                 example={"test1@example.com": "Test user 1", "test2@example.com": "Test user 2"},
     *                 @OA\AdditionalProperties(type="string", description="Name of the recipient.")
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The mail was sent successful",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="size",
     *                  description="Length of the email message",
     *                  type="integer"
     *              )
     *          )
     *     )
     * )
     * @Route("/api/_action/mail-template/send", name="api.action.mail_template.send", methods={"POST"})
     */
    public function send(RequestDataBag $post, Context $context): JsonResponse
    {
        $message = $this->mailService->send($post->all(), $context);

        return new JsonResponse(['size' => mb_strlen($message ? $message->toString() : '')]);
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/mail-template/validate",
     *     summary="Validate a mail content",
     *     description="Validates if content for a mail can be rendered without sending an email.",
     *     operationId="validate",
     *     tags={"Admin API", "Mail Operations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                  "contentHtml",
     *                  "contentPlain",
     *             },
     *             @OA\Property(
     *                 property="contentHtml",
     *                 description="The content of the mail in HTML format.",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="contentPlain",
     *                 description="The content of the mail as plain text.",
     *                 type="string",
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating the mail content was rendered successfully."
     *     )
     * )
     * @Route("/api/_action/mail-template/validate", name="api.action.mail_template.validate", methods={"POST"})
     *
     * @throws StringTemplateRenderingException
     */
    public function validate(RequestDataBag $post, Context $context): JsonResponse
    {
        $this->templateRenderer->initialize();
        $this->templateRenderer->render($post->get('contentHtml', ''), [], $context);
        $this->templateRenderer->render($post->get('contentPlain', ''), [], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @OA\Post(
     *     path="/_action/mail-template/build",
     *     summary="Preview a mail template",
     *     description="Generates a preview of a mail template.",
     *     operationId="build",
     *     tags={"Admin API", "Mail Operations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                  "mailTemplateType",
     *                  "mailTemplate"
     *             },
     *             @OA\Property(
     *                 property="mailTemplateType",
     *                 description="Only the property `templateData` is used. It provides additional variables to the templating engine.",
     *                 type="object",
     *                 @OA\Property(
     *                     property="templateData",
     *                     description="An associative array that is handed over to the templating engine and can be used as variables in the mail content.",
     *                     example={"order": {"orderNumber": 5000, "customerName": "Example Customer"}, "messageOfTheDay": "An apple a day keeps the doctor away!"},
     *                     type="object",
     *                     additionalProperties=true
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="mailTemplate",
     *                 description="The content of the mail as plain text.",
     *                 type="object",
     *                 @OA\Property(
     *                     property="contentHtml",
     *                     description="The content of mail mail template in html format.",
     *                     example="Hello {{ order.customerName }}, this is example mail content, the current date is {{ 'now'|date('d/m/Y') }}",
     *                     type="string"
     *                 )
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The rendered preview of the mail template.",
     *          @OA\JsonContent(
     *              type="string"
     *          )
     *     )
     * )
     * @Route("/api/_action/mail-template/build", name="api.action.mail_template.build", methods={"POST"})
     */
    public function build(RequestDataBag $post, Context $context): JsonResponse
    {
        $data = $post->all();
        $templateData = $data['mailTemplateType']['templateData'];

        $this->templateRenderer->enableTestMode();
        $contents['text/html'] = $this->templateRenderer->render($data['mailTemplate']['contentHtml'], $templateData, $context);
        $this->templateRenderer->disableTestMode();

        return new JsonResponse($contents['text/html']);
    }
}
