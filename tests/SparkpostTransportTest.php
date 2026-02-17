<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Part\DataPart;
use Vemcogroup\SparkPostDriver\Transport\SparkpostTransport;

it('sends an email via the Sparkpost Transmissions API', function () {
    [
        'client'       => $client,
        'sentRequests' => $sentRequests,
    ] = createGuzzleClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'results' => ['id' => 'MESSAGE_ID'],
        ], JSON_THROW_ON_ERROR)),
    ]);

    Mail::extend('sparkpost', fn () => new SparkPostTransport($client, 'secret_key'));

    $attachmentA = (new FileFactory)->image('attachment-a.png', 100, 100);
    $attachmentB = (new FileFactory)->image('attachment-b.png', 50, 50);

    $message = Mail::mailer('sparkpost')
        ->to([
            ['name' => 'Recipient A', 'email' => 'recipient-a@example.com'],
            ['name' => 'Recipient B', 'email' => 'recipient-b@example.com'],
        ])
        ->cc([
            ['name' => 'CC A', 'email' => 'cc-a@example.com'],
            ['name' => 'CC B', 'email' => 'cc-b@example.com'],
        ])
        ->bcc([
            ['name' => 'BCC A', 'email' => 'bcc-a@example.com'],
            ['name' => 'BCC B', 'email' => 'bcc-b@example.com'],
        ])
        ->send(new class([$attachmentA, $attachmentB]) extends Mailable {
            public function __construct(private array $attachmentsToSend) {}

            public function build(): Mailable
            {
                foreach ($this->attachmentsToSend as $attachment) {
                    $this->attach($attachment, ['as' => $attachment->name, 'mime' => 'image/png']);
                }

                return $this->html('HTML content')
                    ->text(new HtmlString('Text content'))
                    ->subject('Mail subject')
                    ->from('sender@example.com', 'Sender name')
                    ->replyTo('reply-to@example.com', 'Reply To');
            }
        });

    expect($message)->toBeInstanceOf(SentMessage::class)
        ->and($message->getOriginalMessage()->getHeaders()->getHeaderBody('X-Sparkpost-Transmission-ID'))->toBe('MESSAGE_ID');
    expect($sentRequests)->toHaveCount(1);
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $sentRequests[0]['request'];
    expect($request)
        ->getUri()->toEqual('https://api.sparkpost.com/api/v1/transmissions')
        ->getHeaderLine('authorization')->toBe('secret_key')
        ->getHeaderLine('content-type')->toBe('application/json')
        ->and(json_decode($request->getBody()->getContents(), true))->toEqual([
            'recipients' => [
                ['address' => ['email' => 'recipient-a@example.com', 'name' => 'Recipient A', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],
                ['address' => ['email' => 'recipient-b@example.com', 'name' => 'Recipient B', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],

                ['address' => ['email' => 'cc-a@example.com', 'name' => 'CC A', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],
                ['address' => ['email' => 'cc-b@example.com', 'name' => 'CC B', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],

                ['address' => ['email' => 'bcc-a@example.com', 'name' => 'BCC A', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],
                ['address' => ['email' => 'bcc-b@example.com', 'name' => 'BCC B', 'header_to' => 'recipient-a@example.com,recipient-b@example.com']],
            ],
            'content'    => [
                'subject'     => 'Mail subject',
                'from'        => ['name' => 'Sender name', 'email' => 'sender@example.com'],
                'reply_to'    => 'Reply To <reply-to@example.com>',
                'html'        => 'HTML content',
                'text'        => 'Text content',
                'attachments' => [
                    [
                        'name' => $attachmentA->name,
                        'type' => $attachmentA->getMimeType(),
                        'data' => base64_encode($attachmentA->getContent()),
                    ],
                    [
                        'name' => $attachmentB->name,
                        'type' => $attachmentB->getMimeType(),
                        'data' => base64_encode($attachmentB->getContent()),
                    ],
                ],
                'inline_images' => [],
                'headers'     => [
                    'CC' => 'cc-a@example.com,cc-b@example.com',
                ],
            ],
        ]);
    expect($message->toString())
        ->not->toContain('bcc-a@example.com')
        ->not->toContain('bcc-b@example.com');
});

it('sends embedded images', function () {
    View::addNamespace('test', __DIR__ . '/views');

    [
        'client'       => $client,
        'sentRequests' => $sentRequests,
    ] = createGuzzleClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'results' => ['id' => 'MESSAGE_ID'],
        ], JSON_THROW_ON_ERROR)),
    ]);

    Mail::extend('sparkpost', fn () => new SparkPostTransport($client, 'secret_key'));

    $attachment = (new FileFactory)->image('attachment.png');

    $message = Mail::mailer('sparkpost')
        ->to('recipient@example.com')
        ->send(new class([$attachment]) extends Mailable {
            public function __construct(private array $attachmentsToSend) {}

            public function build(): Mailable
            {
                foreach ($this->attachmentsToSend as $attachment) {
                    $this->attach($attachment, ['as' => $attachment->name, 'mime' => 'image/png']);
                }

                return $this
                    ->subject('Mail subject')
                    ->view('test::mail-with-embed');

            }
        });

    expect($sentAttachments = $message->getSymfonySentMessage()->getOriginalMessage()->getAttachments())
        ->toHaveCount(2);

    /** @var DataPart $inlineImage */
    $inlineImage = collect($sentAttachments)->first(function (DataPart $attachment) {
        return $attachment->getDisposition() === 'inline';
    });

    if (version_compare(Application::VERSION, '12', '>=')) {
        $cid = $inlineImage->getContentId();
    } else {
        $cid = $inlineImage->getName();
    }

    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $sentRequests[0]['request'];

    expect(json_decode($request->getBody()->getContents(), true))
        ->toEqual([
            'recipients' => [
                ['address' => ['email' => 'recipient@example.com', 'name' => '', 'header_to' => 'recipient@example.com']],
            ],
            'content'    => [
                'subject'       => 'Mail subject',
                'from'          => ['name' => 'Example', 'email' => 'hello@example.com'],
                'reply_to'      => null,
                'html'          => sprintf(<<<HTML
                    <html>
                      <body>
                        <img src="cid:%s">
                      </body>
                    </html>
                    HTML, $cid),
                'text'          => null,
                'attachments'   => [
                    [
                        'name' => $attachment->name,
                        'type' => $attachment->getMimeType(),
                        'data' => base64_encode($attachment->getContent()),
                    ],
                ],
                'inline_images' => [
                    [
                        'name' => $cid,
                        'type' => 'image/png',
                        'data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                    ],
                ],
            ],
        ]);
});

it('allows customizing the request body', function () {
    ['client' => $client, 'sentRequests' => $sentRequests] = createGuzzleClient([
        new Response(200, ['Content-Type' => 'application/json'], '{"results":{"id":"MESSAGE_ID"}}'),
    ]);
    Mail::extend('sparkpost', fn () => new SparkPostTransport($client, 'secret_key', [
        'options'     => [
            'click_tracking' => false,
        ],
        'description' => 'Test',
    ]));

    sendSimpleEmail();

    expect($sentRequests)->toHaveCount(1);
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $sentRequests[0]['request'];
    $body    = json_decode($request->getBody()->getContents(), true);
    expect($body['options'])->toEqual(['click_tracking' => false]);
    expect($body['description'])->toEqual('Test');
});

/**
 * @param list<Response> $responses
 *
 * @return array{client: Client, sentRequests: \Illuminate\Support\Collection<int, array{request: \Psr\Http\Message\RequestInterface, response: Response, error: mixed, options: array}>}
 */
function createGuzzleClient(array $responses): array
{
    $handler = HandlerStack::create(new MockHandler($responses));

    $sentRequests = collect();
    $history      = Middleware::history($sentRequests);

    $handler->push($history);

    return [
        'client'       => new Client(['handler' => $handler]),
        'sentRequests' => $sentRequests,
    ];
}

function sendSimpleEmail(): SentMessage
{
    return Mail::mailer('sparkpost')
        ->to([['name' => 'Recipient', 'email' => 'recipient@example.com']])
        ->send(new class extends Mailable {
            public function build(): Mailable
            {
                return $this->html('HTML content')->text(new HtmlString('Text content'));
            }
        });
}

