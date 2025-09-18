# Yii2 Brevo Mailer

Yii2 mailer connector for [Brevo](https://www.brevo.com) (formerly Sendinblue).  
Provides a seamless integration of the **official [brevo-php SDK](https://github.com/getbrevo/brevo-php)** into Yii2.

This component lets you send either **full custom HTML emails** or **Brevo transactional templates**, and you can deliver them through **Brevo’s Transactional REST API** (via the official SDK) **or through Brevo’s SMTP relay**.

Features include dynamic template parameters, custom subjects, attachments, CC/BCC, default sender configuration, and sandbox mode for safe testing.

---

## Installation

Add the package to your project:

```bash
$ composer require jlorente/yii2-brevo
```

Then configure the component in your Yii2 application (for example in `config/main.php` or `common/config/main.php`):
```json
...
    'components' => [
        // ... other configurations ...
        'mailer' => [
            'class' => \jlorente\brevo\Mailer::class,
            'apiKey' => env('BREVO_API_KEY'),
            'defaultSender' => [
                'email' => 'noreply@your-domain.com',
                'name'  => 'My App',
            ],
            'sandboxEmail' => 'dev@your-domain.com', // or an array of addresses
            'guzzleConfig' => [
                'timeout' => 10.0,
                // add proxy or other Guzzle options if needed
            ],
            'useFileTransport' => false,
            'viewPath' => '@app/mail',
        ],
    ],
...
```

---

## Basic Usage

```php
    Yii::$app->mailer->compose()
        ->setTo('user@example.com')
        ->setSubject('Hello from Brevo')
        ->setHtmlBody('<h1>Hello</h1><p>HTML body</p>')
        ->setTextBody('Hello — plain text body')
        ->send();
```

---

## Using Brevo Templates

```php
    Yii::$app->mailer->compose()
        ->setTo('user@example.com')
        ->setTemplateId(123456)
        ->setParams([
            'firstName' => 'John',
            // other template parameters
        ])
        ->send();
```

---

## Attachments

Attach a file from disk:

```php
    Yii::$app->mailer->compose()
        ->setTo('user@example.com')
        ->setSubject('Report attached')
        ->setHtmlBody('<p>Please find the report attached</p>')
        ->attach('/path/report.pdf', [
            'fileName' => 'report.pdf',
            'contentType' => 'application/pdf',
        ])
        ->send();
```

Attach content from memory:

```php
    $binary = file_get_contents('/path/generated.pdf');
    Yii::$app->mailer->compose()
        ->setTo('user@example.com')
        ->setSubject('In-memory attachment')
        ->setHtmlBody('<p>See attached file</p>')
        ->attachContent($binary, [
            'fileName' => 'generated.pdf',
            'contentType' => 'application/pdf',
        ])
        ->send();
```

---

## Inline / Embed Notes

* Methods `embed()` and `embedContent()` exist to satisfy the Yii2 `MessageInterface`.
* **Warning:** the Brevo transactional REST API does **not** support true inline (CID) images.  
  Calling these methods will log a warning and the resource will be sent as a regular attachment, **not** as an inline MIME part.

---

## Sandbox Mode

If `sandboxEmail` (string or array) is configured, every outgoing email is redirected to that address or list of addresses, ignoring the original recipients.  
This is useful for development, staging, or automated tests to prevent accidental delivery.

---

## Technical Notes

* Uses the official `getbrevo/brevo-php` SDK.
* A default sender (`defaultSender`) is applied if no `From` address is set on the message.
* `guzzleConfig` lets you customize the underlying Guzzle HTTP client (timeouts, proxy, etc.).
* After sending, you can access the Brevo response via `Message::getLastResponse()` or check exceptions with `Message::getLastException()`.
* Calls to `embed()` or `embedContent()` will log a Yii warning.

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Add tests if possible.
4. Submit a pull request with a clear description.

---

## License
Copyright &copy; 2025 José Lorente Martín <jose.lorente.martin@gmail.com>.

Licensed under the MIT license. See LICENSE.txt for details.