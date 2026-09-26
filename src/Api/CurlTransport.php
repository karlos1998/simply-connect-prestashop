<?php

namespace SimplyConnect\PrestaShop\Api;

use RuntimeException;

final class CurlTransport implements Transport {
    public function request(string $method, string $url, array $headers, ?array $body = null): HttpResponse {
        $responseHeaders = [];
        $handle = curl_init($url);
        $lines = [];
        foreach ($headers as $name => $value) { $lines[] = $name . ': ' . $value; }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_USERAGENT => 'SimplyConnectPrestaShop/0.1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) { $responseHeaders[trim($parts[0])] = trim($parts[1]); }
                return $length;
            },
        ]);
        if ($body !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
        $contents = curl_exec($handle);
        if ($contents === false) { $message = curl_error($handle); curl_close($handle); throw new RuntimeException($message); }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return new HttpResponse($status, (string) $contents, $responseHeaders);
    }
}
