# Laravel SparkPost Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vemcogroup/laravel-sparkpost-driver.svg?style=flat-square)](https://packagist.org/packages/vemcogroup/laravel-sparkpost-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/vemcogroup/laravel-sparkpost-driver.svg?style=flat-square)](https://packagist.org/packages/vemcogroup/laravel-sparkpost-driver)

## Description

This package allows you to still use SparkPost as MailDriver in Laravel 6.x

This package is inspired by: https://github.com/clarification/sparkpost-laravel-driver and updated with driver from Laravel 5.8.x

## Installation

You can install the package via composer:

```bash
composer require vemcogroup/laravel-sparkpost-driver
```

The package will automatically register its service provider.

## Usage

You will also need to add the sparkpost API Key settings to the array in `config/services.php` and set up the environment key

```php
'sparkpost' => [
    'secret' => env('SPARKPOST_SECRET'),
    'guzzle' => [
        'verify' => true,
        'decode_content' => true,
    ],
    'options' => [
        'open_tracking' => false,
        'click_tracking' => false,
        'transactional' => true,
    ],
],
```

```php
SPARKPOST_SECRET=__Your_key_here__
```

Finally you need to set your mail driver to sparkpost. You can do this by changing the driver in `config/mail.php`

```php
'driver' => env('MAIL_DRIVER', 'sparkpost'),
```

Or by setting the environment variable `MAIL_DRIVER` in your `.env` file

```php
MAIL_DRIVER=sparkpost
```