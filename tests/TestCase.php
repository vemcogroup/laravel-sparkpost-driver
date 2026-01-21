<?php

namespace Vemcogroup\SparkPostDriver\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vemcogroup\SparkPostDriver\SparkPostDriverServiceProvider;

class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SparkPostDriverServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    public function defineEnvironment($app): void
    {
        config()->set('mail.mailers.sparkpost', [
            'transport' => 'sparkpost',
        ]);
    }
}
