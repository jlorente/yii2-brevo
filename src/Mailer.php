<?php
namespace custom\brevo\src;

use yii\mail\BaseMailer;
use yii\mail\MessageInterface;
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Class Mailer
 *
 * Yii2 mailer component that sends messages through the Brevo
 * (formerly Sendinblue) transactional REST API using the official
 * brevo-php SDK.
 *
 * Features:
 *  - Compatible with Yii2's BaseMailer/MessageInterface.
 *  - Supports HTML and text bodies, headers, CC/BCC, templates
 *    (via templateId and params), and file attachments.
 *  - Allows default sender and custom Guzzle client configuration
 *    (timeouts, proxies, etc.).
 *  - **Sandbox mode** via {@see $sandboxEmail}: when set, every
 *    outgoing email is redirected to this address (string) or list
 *    of addresses (array) regardless of the original recipients.
 *    This is useful for staging or automated tests where you want
 *    to prevent real deliveries while still exercising the API.
 *
 * Configuration example in Yii2:
 * ---------------------------------------------------------------
 * 'components' => [
 *     'mailer' => [
 *         'class' => \Homedoctor\Brevo\Mailer::class,
 *         'apiKey' => '<YOUR_BREVO_API_KEY>',
 *         'defaultSender' => ['email' => 'noreply@example.com', 'name' => 'My App'],
 *         'sandboxEmail' => 'dev-inbox@example.com',   // or ['qa@example.com','log@example.com']
 *         'useFileTransport' => false,
 *     ],
 * ],
 *
 * Inline images (CID embeds)
 * ---------------------------------------------------------------
 * The Brevo REST API does NOT support true inline (cid:) images.
 * Any calls to Message::embed() or embedContent() will log a
 * warning and downgrade the resource to a standard attachment.
 * For genuine inline images, either:
 *   1. Use absolute URLs in the HTML body, or
 *   2. Configure Yii2 to use Brevo's SMTP server instead of the
 *      REST API.
 *
 * @property string|array $sandboxEmail  One or more addresses to receive all
 *                                       outgoing messages in sandbox mode.
 *
 * @package jlorente\brevo
 */
class Mailer extends BaseMailer
{
    /** @var string Brevo API key */
    public string $apiKey;

    /** @var array Guzzle extra Config (timeouts, proxy, etc.) */
    public array $guzzleConfig = [];

    /** @var array Default Sender: ['noreply@...' => 'name'] */
    public array $defaultSender = [];

    /** @var TransactionalEmailsApi */
    protected TransactionalEmailsApi $api;

    public $messageClass = Message::class;

    /**
     *
     * @var string|array
     */
    public $sandboxEmail;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $this->apiKey);

        $client = new GuzzleClient($this->guzzleConfig);
        $this->api = new TransactionalEmailsApi($client, $config);
    }

    /**
     * @param MessageInterface|Message $message
     */
    protected function sendMessage($message): bool
    {
        if (!$message->getSender() && $this->defaultSender) {
            $message->setFrom($this->defaultSender);
        }

        if ($this->sandboxEmail) {
            $message->setTo($this->sandboxEmail);
        }

        $payload = $message->toSendSmtpEmail();

        //var_dump($payload);die;
        try {
            $result = $this->api->sendTransacEmail($payload);
            $message->setLastResponse($result);
            return true;
        } catch (\Throwable $e) {
            \Yii::error(['brevo.mailer' => $e->getMessage()], __METHOD__);
            $message->setLastException($e);
            return false;
        }
    }

    /**
     * @return TransactionalEmailsApi
     */
    public function getApi(): TransactionalEmailsApi
    {
        return $this->api;
    }
}