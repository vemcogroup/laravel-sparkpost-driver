<?php

use Illuminate\Mail\Mailer;
use Vemcogroup\SparkPostDriver\Transport\SparkPostTransport;

it('registers the Sparkpost transport with the Laravel MailManager', function () {
    $manager = app('mail.manager');
    config()->set('services.sparkpost', [
        'secret' => 'SPARKPOST_SECRET',
    ]);

    $mailer = $manager->driver('sparkpost');

    expect($mailer)->toBeInstanceOf(Mailer::class);
    $transport = $mailer->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(SparkPostTransport::class)
        ->getEndpoint()->toBe('https://api.sparkpost.com/api/v1');
});

it('passes any configured options to the transport', function () {
    $manager = app('mail.manager');
    config()->set('services.sparkpost', [
        'secret'  => 'SPARKPOST_SECRET',
        'options' => ['open_tracking' => true],
    ]);

    $mailer = $manager->driver('sparkpost');

    expect($mailer->getSymfonyTransport()->getOptions())->toBe([
        'open_tracking' => true,
    ]);
});

it('allows customizing the Sparkpost endpoint', function () {
    $manager = app('mail.manager');
    config()->set('services.sparkpost', [
        'secret'  => 'SPARKPOST_SECRET',
        'options' => [
            'endpoint' => $endpoint = 'https://api.eu.sparkpost.com/api/v1',
        ],
    ]);

    $mailer = $manager->driver('sparkpost');

    expect($mailer->getSymfonyTransport()->getEndpoint())->toBe($endpoint);
});

it('passes any configured guzzle options to the Guzzle client', function () {
    config()->set('services.sparkpost', [
        'secret' => 'SPARKPOST_SECRET',
        'guzzle' => [
            'timeout'         => 100,
            'connect_timeout' => 20,
        ],
    ]);

    $manager   = app('mail.manager');
    $transport = $manager->driver('sparkpost')->getSymfonyTransport();

    $guzzleClient = (new \ReflectionClass($transport))->getProperty('client')->getValue($transport);

    expect($guzzleClient->getConfig())->toMatchArray([
        'timeout'         => 100,
        'connect_timeout' => 20,
    ]);
});
