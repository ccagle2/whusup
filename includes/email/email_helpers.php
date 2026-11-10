<?php

use Aws\Ses\SesClient;

/*
|--------------------------------------------------------------------------
| Whusup Email Helpers
|--------------------------------------------------------------------------
|
| Centralized email delivery and notification-email helpers.
|
| Keep AWS SES delivery details here rather than duplicating them throughout
| controllers and AJAX endpoints.
|
| Event-specific notification functions can be added below as Whusup grows.
|
*/

/*
|--------------------------------------------------------------------------
| Email configuration
|--------------------------------------------------------------------------
*/

if (!defined('WHUSUP_EMAIL_FROM')) {
    define(
        'WHUSUP_EMAIL_FROM',
        'Whusup <noreply@whusup.com>'
    );
}

if (!defined('WHUSUP_SITE_URL')) {
    define(
        'WHUSUP_SITE_URL',
        'https://whusup.com'
    );
}


/**
 * Send a standard Whusup email through AWS SES.
 *
 * @throws Throwable if SES delivery fails.
 */
function sendWhusupEmail(
    SesClient $ses,
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $textBody
): void {
    $toEmail = trim($toEmail);
    $subject = trim($subject);

    if ($toEmail === '') {
        throw new InvalidArgumentException(
            'Email recipient is missing.'
        );
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            'Email recipient is invalid.'
        );
    }

    if ($subject === '') {
        throw new InvalidArgumentException(
            'Email subject is missing.'
        );
    }

    $ses->sendEmail([
        'Source' => WHUSUP_EMAIL_FROM,

        'Destination' => [
            'ToAddresses' => [
                $toEmail
            ],
        ],

        'Message' => [
            'Subject' => [
                'Data' => $subject,
                'Charset' => 'UTF-8',
            ],

            'Body' => [
                'Html' => [
                    'Data' => $htmlBody,
                    'Charset' => 'UTF-8',
                ],

                'Text' => [
                    'Data' => $textBody,
                    'Charset' => 'UTF-8',
                ],
            ],
        ],
    ]);
}


/**
 * Shorten text for use in an email preview.
 */
function whusupEmailPreview(
    string $text,
    int $maxLength = 220
): string {
    $text = trim(
        preg_replace('/\s+/', ' ', $text) ?? ''
    );

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        return rtrim(
            mb_substr(
                $text,
                0,
                max(1, $maxLength - 1),
                'UTF-8'
            )
        ) . '…';
    }

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(
        substr(
            $text,
            0,
            max(1, $maxLength - 3)
        )
    ) . '...';
}


/**
 * Send an email to a post owner when their post receives its first comment.
 *
 * Expected $recipient:
 * - email
 * - display_name
 *
 * Expected $commenter:
 * - display_name
 *
 * Expected $post:
 * - id
 * - title
 * - body
 * - tag
 *
 * Expected $comment:
 * - comment_body
 */
function sendFirstCommentNotification(
    SesClient $ses,
    array $recipient,
    array $commenter,
    array $post,
    array $comment
): void {
    $recipientEmail = trim(
        (string) ($recipient['email'] ?? '')
    );

    $recipientName = trim(
        (string) ($recipient['display_name'] ?? '')
    );

    $commenterName = trim(
        (string) ($commenter['display_name'] ?? '')
    );

    $commentBody = trim(
        (string) ($comment['comment_body'] ?? '')
    );

    $postId = (int) ($post['id'] ?? 0);

    if ($recipientEmail === '' || $postId <= 0) {
        return;
    }

    if ($commenterName === '') {
        $commenterName = 'Someone';
    }

    /*
     * getPostSlug() comes from includes/posts/post_helpers.php.
     */
    $postSlug = getPostSlug($post);

    $postUrl =
        WHUSUP_SITE_URL
        . '/post/'
        . rawurlencode((string) $postId)
        . '/'
        . rawurlencode($postSlug);

    $commentPreview = whusupEmailPreview(
        $commentBody,
        220
    );

    /*
     * Escape everything inserted into the HTML email.
     */
    $safeRecipientName = htmlspecialchars(
        $recipientName,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeCommenterName = htmlspecialchars(
        $commenterName,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeCommentPreview = htmlspecialchars(
        $commentPreview,
        ENT_QUOTES,
        'UTF-8'
    );

    $safePostUrl = htmlspecialchars(
        $postUrl,
        ENT_QUOTES,
        'UTF-8'
    );

    $subject =
        $commenterName
        . ' commented on your post';

    $greeting = $safeRecipientName !== ''
        ? 'Hi ' . $safeRecipientName . ','
        : 'Hi,';

    $htmlBody = '
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(
        $subject,
        ENT_QUOTES,
        'UTF-8'
    ) . '</title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f3f4f6;
    font-family:Arial,Helvetica,sans-serif;
    color:#111827;
">

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="background:#f3f4f6;padding:28px 16px;"
    >
        <tr>
            <td align="center">

                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        max-width:600px;
                        background:#ffffff;
                        border-radius:16px;
                        overflow:hidden;
                        box-shadow:0 6px 20px rgba(17,24,39,0.08);
                    "
                >
                    <tr>
                        <td style="
                            padding:28px 28px 10px;
                            font-size:24px;
                            font-weight:700;
                        ">
                            Whusup
                        </td>
                    </tr>

                    <tr>
                        <td style="
                            padding:12px 28px 28px;
                            font-size:16px;
                            line-height:1.6;
                        ">

                            <p style="margin:0 0 18px;">
                                ' . $greeting . '
                            </p>

                            <p style="margin:0 0 18px;">
                                <strong>'
                                    . $safeCommenterName .
                                '</strong>
                                commented on your post.
                            </p>

                            ' .
                            (
                                $safeCommentPreview !== ''
                                    ? '
                            <div style="
                                margin:20px 0;
                                padding:16px 18px;
                                background:#f9fafb;
                                border-left:4px solid #d1d5db;
                                border-radius:8px;
                                color:#374151;
                            ">
                                ' . $safeCommentPreview . '
                            </div>'
                                    : ''
                            )
                            . '

                            <p style="
                                margin:26px 0 18px;
                                text-align:center;
                            ">
                                <a
                                    href="' . $safePostUrl . '"
                                    style="
                                        display:inline-block;
                                        padding:12px 22px;
                                        background:#111827;
                                        color:#ffffff;
                                        text-decoration:none;
                                        border-radius:999px;
                                        font-weight:700;
                                    "
                                >
                                    View Comment
                                </a>
                            </p>

                            <p style="
                                margin:24px 0 0;
                                color:#6b7280;
                                font-size:13px;
                                line-height:1.5;
                            ">
                                You received this email because email
                                notifications are enabled for your Whusup
                                account.
                            </p>

                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>';

    $textGreeting = $recipientName !== ''
        ? 'Hi ' . $recipientName . ','
        : 'Hi,';

    $textBody =
        $textGreeting
        . "\n\n"
        . $commenterName
        . " commented on your post."
        . "\n\n";

    if ($commentPreview !== '') {
        $textBody .=
            '"' . $commentPreview . '"'
            . "\n\n";
    }

    $textBody .=
        "View the comment:\n"
        . $postUrl
        . "\n\n"
        . "You received this email because email notifications "
        . "are enabled for your Whusup account.";

    sendWhusupEmail(
        $ses,
        $recipientEmail,
        $subject,
        $htmlBody,
        $textBody
    );
}