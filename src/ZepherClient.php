<?php

namespace Zepher\PHPClient;

class ZepherClient
{
    private $appKey;

    public function __construct(string $appKeyOrJwt)
    {
        $this->appKey = $appKeyOrJwt;
    }


    public function get(string $uri, array $data = [])
    {
        $uri = $data ? ($uri . '?' . http_build_query($data)) : $uri;

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' .  $this->appKey
        ]);

        return $this->return($ch);
    }


    public function post(string $uri, array $data)
    {
        $ch = curl_init($uri);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->appKey
        ]);
        return $this->return($ch);
    }


    public function delete(string $uri)
    {
        $ch = curl_init($uri);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->appKey
        ]);

        return $this->return($ch);
    }

    
    private function return($ch): array
    {
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return json_decode($response, true);
    }
}
