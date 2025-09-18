<?php
namespace custom\brevo\src;

use http\Exception\InvalidArgumentException;
use yii\mail\BaseMessage;
use Brevo\Client\Model\SendSmtpEmail;

/**
 * Class Message
 *
 * Yii2 mail message implementation for the Brevo transactional API.
 *
 * This class adapts Yii's MessageInterface to Brevo's v3 REST API using
 * the official brevo-php SDK. It supports standard features such as
 * setting sender/recipients, HTML/text bodies, headers, template IDs,
 * parameters, and file attachments.
 *
 * Inline images (CID embeds)
 * --------------------------------------------------------------------
 * The Brevo transactional API does NOT support true inline (cid:)
 * images. The required MessageInterface methods `embed()` and
 * `embedContent()` are implemented only to satisfy the Yii2 contract.
 * When called, they will:
 *   • Log a Yii warning (and trigger a PHP E_USER_WARNING in YII_ENV_DEV).
 *   • Attach the file/content as a regular attachment instead of an
 *     inline MIME part.
 *
 * Developers who need real inline images should either:
 *   1. Use absolute image URLs in the HTML body, or
 *   2. Configure Yii2 to send mail via Brevo’s SMTP service instead of
 *      the REST API, which supports standard MIME inline attachments.
 *
 * @package jlorente\brevo
 */
class Message extends BaseMessage
{
    public const LOGNAME = 'Brevo Mailer';

    protected $from = null;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected ?string $subject = null;
    protected ?string $htmlBody = null;
    protected ?string $textBody = null;
    protected $replyTo = null;
    protected array $headers = [];
    protected array $params = [];
    protected ?int $templateId = null;
    protected array $attachments = [];

    protected $lastResponse = null;
    protected $lastException = null;

    /**
     * @param array|string $from
     * @return $this
     */
    public function setFrom($from): self
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getSender()
    {
        return $this->from;
    }

    /**
     * @param array|string $to
     * @return $this
     */
    public function setTo($to): self
    {
        $this->to = $to;
        return $this;
    }

    /**
     * @param array|string $cc
     * @return $this
     */
    public function setCc($cc): self
    {
        $this->cc = $cc;
        return $this;
    }

    /**
     * @param array|string $bcc
     * @return $this
     */
    public function setBcc($bcc): self
    {
        $this->bcc = $bcc;
        return $this;
    }

    /**
     * @param $subject
     * @return $this
     */
    public function setSubject($subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @param $html
     * @return $this
     */
    public function setHtmlBody($html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    /**
     * @param $text
     * @return $this
     */
    public function setTextBody($text): self
    {
        $this->textBody = $text;
        return $this;
    }

    /**
     * @param array|string $replyTo
     * @return $this
     */
    public function setReplyTo($replyTo): self
    {
        $this->replyTo = $replyTo;
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function addHeader($name, $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function setTemplateId(?int $id): self
    {
        $this->templateId = $id;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param $filePath
     * @param array $options
     * @return $this|Message
     */
    public function attach($fileName, array $options = [])
    {
        $displayName = $options['fileName'] ?? basename($fileName);
        $contentType = $options['contentType'] ?? (function($p) {
            $mime = function_exists('mime_content_type') ? @mime_content_type($p) : null;
            return $mime ?: 'application/octet-stream';
        })($fileName);

        $binary = @file_get_contents($fileName);
        if ($binary === false) {
            throw new \RuntimeException("No se pudo leer el adjunto: {$fileName}");
        }

        $this->attachments[] = [
            'name'    => $displayName,
            'type'    => $contentType,
            'content' => base64_encode($binary),
        ];
        return $this;
    }

    /**
     * @param $fileName
     * @param array $options
     * @return $this|Message|string
     */
    public function embed($fileName, array $options = []): Message|string|static
    {
        \Yii::warning('Brevo transactional API does not support true inline (CID) images. '
            . 'The file will be sent as a regular attachment instead.', self::LOGNAME);
        return $this->attach($fileName, $options);
    }

    /**
     * @param $content
     * @param array $options
     * @return $this|Message
     */
    public function attachContent($content, array $options = []): Message|static
    {
        if (empty($options['fileName'])) {
            throw new \InvalidArgumentException('attachContent requiere options["fileName"].');
        }
        $displayName = $options['fileName'];
        $contentType = $options['contentType'] ?? 'application/octet-stream';

        $this->attachments[] = [
            'name'    => $displayName,
            'type'    => $contentType,
            'content' => base64_encode($content),
        ];
        return $this;
    }

    /**
     * @param $content
     * @param array $options
     * @return Message|string|$this
     */
    public function embedContent($content, array $options = []): Message|string|static
    {
        \Yii::warning('Brevo transactional API does not support true inline (CID) images. '
            . 'The file will be sent as a regular attachment instead.', self::LOGNAME);
        return $this->attachContent($content, $options);
    }

    /**
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @param $charset
     * @return $this|Message
     */
    public function setCharset($charset): Message|static
    {
        \Yii::warning('Brevo uses UTF-8 by default', self::LOGNAME);
        return $this;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return 'UTF-8';
    }

    /**
     * @return array|string
     */
    public function getTo(): array|string
    {
        return $this->to;
    }

    /**
     * @return array|null[]
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    /**
     * @return array|null[]
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    /**
     * @return array|string
     */
    public function getCc(): array|string
    {
        return $this->cc;
    }

    /**
     * @return array|string
     */
    public function getBcc(): array|string
    {
        return $this->bcc;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->htmlBody ?? $this->textBody ?? '';
    }

    /**
     * @return SendSmtpEmail
     */
    public function toSendSmtpEmail(): SendSmtpEmail
    {
        $body = new SendSmtpEmail();

        if ($this->templateId) {
            $body->setTemplateId($this->templateId);
            if ($this->params) {
                $body->setParams($this->params);
            }
        } else {
            if ($this->htmlBody) {
                $body->setHtmlContent($this->htmlBody);
            }
            if ($this->textBody) {
                $body->setTextContent($this->textBody);
            }
            if ($this->subject) {
                $body->setSubject($this->subject);
            }
        }

        if ($this->from) {
            $body->setSender($this->normalizeAddr($this->from));
        }
        if ($this->to) {
            $body->setTo($this->normalizeAddrList($this->to));
        }
        if ($this->cc) {
            $body->setCc($this->normalizeAddrList($this->cc));
        }
        if ($this->bcc) {
            $body->setBcc($this->normalizeAddrList($this->bcc));
        }
        if ($this->replyTo) {
            $body->setReplyTo($this->normalizeAddrList($this->replyTo));
        }
        if ($this->headers) {
            $body->setHeaders($this->headers);
        }
        if ($this->attachments) {
            $body->setAttachment($this->attachments);
        }

        return $body;
    }

    /**
     * @param $list
     * @return array
     */
    protected function normalizeAddrList($list): array
    {
        $out = [];
        if (is_string($list)) {
            $out[] = ['email' => $list];
        } elseif (is_array($list)) {
            foreach ($list as $email => $name) {
                $addr = [];
                if (is_string($email)) {
                    $addr['email'] = $email;
                    $addr['name'] = $name;
                } else {
                    $addr['email'] = $name;
                }
                $out[] = $addr;
            }
        }
        return $out;
    }

    /**
     * @param $addr
     * @return array|string[]
     */
    protected function normalizeAddr($addr): array
    {
        $out = [];
        if (is_string($addr)) {
            return ['email' => $addr];
        } elseif (is_array($addr)) {
            return ['email' => key($addr), 'name' => current($addr)];
        }

        throw new InvalidArgumentException('Invalid email address format');
    }

    /**
     * @param $resp
     * @return void
     */
    public function setLastResponse($resp): void
    {
        $this->lastResponse = $resp;
    }

    /**
     * @return mixed|null
     */
    public function getLastResponse(): mixed
    {
        return $this->lastResponse;
    }

    /**
     * @param $e
     * @return void
     */
    public function setLastException($e): void
    {
        $this->lastException = $e;
    }

    /**
     * @return mixed|null
     */
    public function getLastException(): mixed
    {
        return $this->lastException;
    }

    /**
     * @return array|string[]
     */
    protected function getNormalizedFrom(): array
    {
        $from = $this->from;

        if (is_string($from)) {
            return ['email' => $from];
        }

        if (is_array($from)) {
            return ['email' => $from[0], 'name' => $from[1] ?? null];
        }

        throw new \InvalidArgumentException('Invalid "from" format.');
    }
}