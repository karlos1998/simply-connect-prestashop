<?php

namespace SimplyConnect\PrestaShop;

final class TemplateRenderer {
    /** @param array<string,scalar|null> $values */
    public function render(string $template, array $values): string {
        $replace = [];
        foreach (self::tokens() as $token) { $replace['{' . $token . '}'] = preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) ($values[$token] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? ''; }
        return trim(strtr($template, $replace));
    }
    /** @return list<string> */
    public static function tokens(): array { return ['order_reference','customer_first_name','customer_last_name','order_total','order_status','shop_name']; }
}
