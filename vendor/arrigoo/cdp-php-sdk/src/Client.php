<?php

namespace Arrigoo\ArrigooCdpSdk;

class Client extends ClientBase
{
    public function getSegments(): array
    {
        $response = $this->get('segment', [
            'headers' => $this->getHeaders(),
        ]);
        return $response;
    }
    public static function create(
        string $apiUrl,
        string $user,
        string $password,
    ): self
    {
        $rBody = parent::authInit($apiUrl, $user, $password);
        return new self(
            $rBody['refreshToken'], 
            $apiUrl, 
            $rBody['token'],
            $rBody['expire'],
        );
    }
}