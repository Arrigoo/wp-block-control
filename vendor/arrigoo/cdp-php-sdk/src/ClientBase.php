<?php

namespace Arrigoo\ArrigooCdpSdk;

use GuzzleHttp\Client as GuzzleClient;

abstract class ClientBase
{
    const API_VERSION = 'v1';
    protected GuzzleClient $client;

    public function __construct(
        protected string $apiSecret,
        protected string $apiUrl,
        protected string $apiKey = '',
        protected string $keyExpiresAt = '',
    )
    {
        $this->client = self::getGuzzleClient($apiUrl);
    }

    protected function get(string $path): array
    {
        $response = $this->client->get(self::API_VERSION . '/' . $path, [
            'headers' => $this->getHeadersWithKey(),
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }

    // Refresh the API key.
    public function renewApiKey(): void
    {
        $headers = $this->getHeaders();
        $headers['X-Refresh-Token'] = $this->apiSecret;
        $response = $this->client->post('security/refresh', [
            'headers' => $headers,
        ]);
        $rBody = json_decode($response->getBody()->getContents(), true);
        $this->apiKey = $rBody['access_token'];
        $this->keyExpiresAt = $rBody['expiry'];
    }

    /**
     * Retrieve the API secret to renew the API key.
     */
    public function getSecret(): string
    {
        return $this->apiSecret;
    }

    public function getSecretExpireTime(): int
    {
        return time() + $this->keyExpiresAt;
    }

    protected function getHeadersWithKey(): array
    {
        $headers = $this->getHeaders();
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        return $headers;
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get a new instance of the Guzzle client.
     *
     * @param string $apiUrl
     * @return GuzzleClient
     */
    protected static function getGuzzleClient(string $apiUrl): GuzzleClient
    {
        return new GuzzleClient([
            'base_uri' => $apiUrl,
        ]);
    }

    /**
     * Factory method to create a new instance of the client.
     *
     * @param string $apiUrl
     * @param string $user
     * @param string $password
     * @return static
     */
    protected static function authInit(
        string $apiUrl,
        string $user,
        string $password,
    ): array
    {
        $gClient = self::getGuzzleClient($apiUrl);
        $response = $gClient->post('auth/api', [
            'json' => [
                'key' => $user,
                'secret' => $password,
            ],
        ]);
        $rBody = json_decode($response->getBody()->getContents(), true);
        return $rBody;
    }
}
