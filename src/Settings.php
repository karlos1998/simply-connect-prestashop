<?php

namespace SimplyConnect\PrestaShop;

use Configuration;
use RuntimeException;

final class Settings {
    public const API_KEY = 'SC_SMS_API_KEY';
    public const ENDPOINT = 'SC_SMS_ENDPOINT';
    public const ENDPOINTS = 'SC_SMS_ENDPOINTS';
    public const STATUSES = 'SC_SMS_STATUSES';
    public const TEMPLATES = 'SC_SMS_TEMPLATES';
    public const CRON_TOKEN = 'SC_SMS_CRON_TOKEN';
    public const SITE_ID = 'SC_SMS_SITE_ID';

    public function baseUrl(): string {
        $override = defined('SIMPLY_CONNECT_API_BASE_URL') ? (string) constant('SIMPLY_CONNECT_API_BASE_URL') : (string) getenv('SIMPLY_CONNECT_API_BASE_URL');
        return $override !== '' ? rtrim($override, '/') : 'https://api.simply-connect.ovh';
    }
    public function apiKey(): string {
        if (defined('SIMPLY_CONNECT_API_KEY')) { return trim((string) constant('SIMPLY_CONNECT_API_KEY')); }
        $environment = trim((string) getenv('SIMPLY_CONNECT_API_KEY'));
        if ($environment !== '') { return $environment; }
        $value = (string) Configuration::get(self::API_KEY);
        return $value === '' ? '' : $this->decrypt($value);
    }
    public function endpointId(): string { return (string) Configuration::get(self::ENDPOINT); }
    /** @return list<int> */
    public function statuses(): array { $data = json_decode((string) Configuration::get(self::STATUSES), true); return is_array($data) ? array_values(array_map('intval', $data)) : []; }
    /** @return array<string,string> */
    public function templates(): array { $data = json_decode((string) Configuration::get(self::TEMPLATES), true); return is_array($data) ? array_filter($data, 'is_string') : []; }
    public function template(int $stateId): string { return $this->templates()[(string) $stateId] ?? ''; }
    /** @return list<array{id:string,name:string,phoneNumber:?string,enabled:bool}> */
    public function endpoints(): array { $data = json_decode((string) Configuration::get(self::ENDPOINTS), true); return is_array($data) ? array_values($data) : []; }
    /** @param list<array{id:string,name:string,phoneNumber:?string,enabled:bool}> $endpoints */
    public function saveEndpoints(array $endpoints): void { Configuration::updateValue(self::ENDPOINTS, json_encode($endpoints, JSON_UNESCAPED_UNICODE)); }
    /** @param list<int> $statuses @param array<string,string> $templates */
    public function save(string $apiKey, string $endpoint, array $statuses, array $templates): void {
        if ($apiKey !== '' && !defined('SIMPLY_CONNECT_API_KEY')) { Configuration::updateValue(self::API_KEY, $this->encrypt($apiKey)); }
        Configuration::updateValue(self::ENDPOINT, $endpoint);
        Configuration::updateValue(self::STATUSES, json_encode(array_values(array_unique(array_map('intval', $statuses)))));
        Configuration::updateValue(self::TEMPLATES, json_encode($templates, JSON_UNESCAPED_UNICODE));
    }
    public function cronToken(): string { return (string) Configuration::get(self::CRON_TOKEN); }
    public static function initialize(): void {
        if (!Configuration::get(self::CRON_TOKEN)) { Configuration::updateValue(self::CRON_TOKEN, bin2hex(random_bytes(24))); }
        if (!Configuration::get(self::SITE_ID)) { Configuration::updateValue(self::SITE_ID, self::uuid()); }
    }
    public static function clear(): void { foreach ([self::API_KEY,self::ENDPOINT,self::ENDPOINTS,self::STATUSES,self::TEMPLATES,self::CRON_TOKEN,self::SITE_ID] as $key) { Configuration::deleteByName($key); } }
    public static function uuid(): string { $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); }
    private function encrypt(string $value): string {
        if (!function_exists('sodium_crypto_secretbox')) { throw new RuntimeException('The Sodium PHP extension is required.'); }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, $this->key()));
    }
    private function decrypt(string $value): string {
        if (!str_starts_with($value, 'v1:') || !function_exists('sodium_crypto_secretbox_open')) { throw new RuntimeException('The stored API key cannot be decrypted.'); }
        $payload = base64_decode(substr($value, 3), true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { throw new RuntimeException('The stored API key is invalid.'); }
        $plain = sodium_crypto_secretbox_open(substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());
        if ($plain === false) { throw new RuntimeException('The stored API key cannot be decrypted.'); }
        return $plain;
    }
    private function key(): string { return hash('sha256', (defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : 'prestashop') . '|simply-connect-prestashop', true); }
}
