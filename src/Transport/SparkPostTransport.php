<?php

namespace Vemcogroup\SparkPostDriver\Transport;

use Swift_Mime_SimpleMessage;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Mail\Transport\Transport;

/**
 * This is a direct copy of the driver included in Laravel 5.8.x
 * https://github.com/laravel/framework/blob/5.8/src/Illuminate/Mail/Transport/SparkPostTransport.php
 */
class SparkPostTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * The SparkPost API key.
     *
     * @var string
     */
    protected $key;

    /**
     * The SparkPost transmission options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new SparkPost transport instance.
     *
     * @param ClientInterface $client
     * @param  string  $key
     * @param  array  $options
     * @return void
     */
    public function __construct(ClientInterface $client, $key, $options = [])
    {
        $this->key = $key;
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $recipients = $this->getRecipients($message);

        $bcc = $message->getBcc();
        $message->setBcc([]);

        $response = $this->client->request('POST', $this->getEndpoint() . '/transmissions', [
            'headers' => [
                'Authorization' => $this->key,
            ],
            'json' => array_merge([
                'recipients' => $recipients,
                'content' => [
                    'email_rfc822' => $message->toString(),
                ],
            ], $this->options),
        ]);

        $message->getHeaders()->addTextHeader(
            'X-SparkPost-Transmission-ID', $this->getTransmissionId($response)
        );

        $this->sendPerformed($message);

        $message->setBcc($bcc);

        return $this->numberOfRecipients($message);
    }

    public function deleteSupression($email): JsonResponse
    {
        try {
            $response = $this->client->request('DELETE', $this->getEndpoint() . '/suppression-list/' . $email, [
                'headers' => [
                    'Authorization' => $this->key,
                ],
            ]);

            return response()->json([
                'code' => $response->getStatusCode(),
                'message' => 'Recipient has been removed from supression list',
            ]);
        } catch(\Exception $e) {
            $message = 'An error occured';

            if ($e->getCode() === 403) {
                $message = 'Recipient could not be removed - Compliance';
            }

            if ($e->getCode() === 404) {
                $message = 'Recipient could not be found';
            }

            return response()->json([
                'code' => $e->getCode(),
                'message' => $message,
            ]);
        }
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * Note that SparkPost still respects CC, BCC headers in raw message itself.
     *
     * @param  \Swift_Mime_SimpleMessage $message
     * @return array
     */
    protected function getRecipients(Swift_Mime_SimpleMessage $message)
    {
        $recipients = [];

        foreach ((array) $message->getTo() as $email => $name) {
            $recipients[] = ['address' => compact('name', 'email')];
        }

        foreach ((array) $message->getCc() as $email => $name) {
            $recipients[] = ['address' => compact('name', 'email')];
        }

        foreach ((array) $message->getBcc() as $email => $name) {
            $recipients[] = ['address' => compact('name', 'email')];
        }

        return $recipients;
    }

    /**
     * Get the transmission ID from the response.
     *
     * @param  Response  $response
     * @return string
     */
    protected function getTransmissionId($response)
    {
        return object_get(
            json_decode($response->getBody()->getContents()), 'results.id'
        );
    }

    /**
     * Get the API key being used by the transport.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set the API key being used by the transport.
     *
     * @param  string  $key
     * @return string
     */
    public function setKey($key)
    {
        return $this->key = $key;
    }

    /**
     * Get the SparkPost API endpoint.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return ($this->getOptions()['endpoint'] ?? 'https://api.sparkpost.com/api/v1');
    }

    /**
     * Get the transmission options being used by the transport.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set the transmission options being used by the transport.
     *
     * @param  array  $options
     * @return array
     */
    public function setOptions(array $options)
    {
        return $this->options = $options;
    }
}
