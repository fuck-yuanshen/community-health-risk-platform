<?php

declare(strict_types=1);

namespace App\Core;

final class Crypto
{
    private string $key;

    public function __construct(string $key)
    {
        $hash = hash('sha256', $key, true);
        $this->key = $hash;
    }

    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return null;
        }

        return base64_encode($iv . $ciphertext);
    }

    public function decrypt(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }
}
