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
        try {
            $body = $response->getBody()->getContents();
            $rBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
        return $rBody ?? [];
    }

    // Refresh the API key.
    public function renewApiKey(): void
    {
        $headers = $this->getHeaders();
        $headers['X-Refresh-Token'] = $this->apiSecret;
        $response = $this->client->post('security/refresh', [
            'headers' => $headers,
        ]);
        try {
            $rBody = json_decode($response->getBody()->getContents(), true,  512, JSON_THROW_ON_ERROR);
            $this->apiKey = $rBody['access_token'];
            $this->keyExpiresAt = $rBody['expiry'];
        } catch (\JsonException $e) {
            // Just no access.
        }
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
        $body = $response->getBody()->getContents();
        try {
            $rBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
        return $rBody ?? [];
    }
}
