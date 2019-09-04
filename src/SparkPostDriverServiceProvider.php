<?php

namespace Vemcogroup\SparkPostDriver;

use GuzzleHttp\Client;
use Illuminate\Mail\TransportManager;
use Illuminate\Support\ServiceProvider;
use Vemcogroup\SparkPostDriver\Transport\SparkPostTransport;

class SparkPostDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {

    }

    public function register(): void
    {
        $this->app->extend('swift.transport', function(TransportManager $manager) {
            $manager->extend('sparkpost', function() {
                $config = config('services.sparkpost', []);
                $sparkpostOptions = $config['options'] ?? [];
                $guzzleOptions = $config['guzzle'] ?? [];
                $client = $this->app->make(Client::class, $guzzleOptions);

                return new SparkPostTransport($client, $config['secret'], $sparkpostOptions);
            });

            return $manager;
        });
    }
}
