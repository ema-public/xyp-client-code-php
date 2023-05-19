<?php

namespace App\Http\XypSign;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class Sign
{
    private $keyPath;
    private $accessToken;
    private $timestamp;

    public function __construct($keyPath, $accessToken, $timestamp)
    {
        $this->keyPath = $keyPath;
        $this->accessToken = $accessToken;
        $this->timestamp = $timestamp;
    }

    public function sign()
    {
        $pkey = file_get_contents(base_path($this->keyPath));
        $openr = openssl_sign($this->accessToken . "." . $this->timestamp, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return [
          'accessToken' => $this->accessToken,
          'timeStamp' => $this->timestamp,
          'signature' => base64_encode($signature)
        ];
    }
}
