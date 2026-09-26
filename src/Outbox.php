<?php

namespace SimplyConnect\PrestaShop;

use Configuration;
use Db;
use RuntimeException;

final class Outbox {
    public static function install(): bool {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'simply_connect_sms` (
            `id_simply_connect_sms` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL, `trigger_type` VARCHAR(20) NOT NULL,
            `from_status` INT UNSIGNED NULL, `to_status` INT UNSIGNED NULL,
            `endpoint_id` VARCHAR(36) NOT NULL, `recipient` VARCHAR(32) NOT NULL, `body` TEXT NOT NULL,
            `idempotency_key` VARCHAR(191) NOT NULL, `status` VARCHAR(40) NOT NULL,
            `message_id` VARCHAR(36) NULL, `dispatch_id` VARCHAR(36) NULL, `command_id` VARCHAR(36) NULL,
            `correlation_id` VARCHAR(100) NULL, `error_kind` VARCHAR(40) NULL, `problem_code` VARCHAR(100) NULL,
            `error_message` TEXT NULL, `retry_after` INT UNSIGNED NULL, `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `sync_attempts` INT UNSIGNED NOT NULL DEFAULT 0, `next_attempt_at` DATETIME NULL, `locked_at` DATETIME NULL,
            `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_simply_connect_sms`), UNIQUE KEY `uniq_idempotency` (`idempotency_key`),
            KEY `order_history` (`id_order`,`date_add`), KEY `queue` (`status`,`next_attempt_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return (bool) Db::getInstance()->execute($sql);
    }
    public static function uninstall(): bool { return (bool) Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'simply_connect_sms`'); }

    public function enqueue(int $orderId, string $trigger, ?int $from, ?int $to, string $endpoint, string $recipient, string $body): int {
        $now = gmdate('Y-m-d H:i:s');
        $ok = Db::getInstance()->insert('simply_connect_sms', [
            'id_order'=>$orderId,'trigger_type'=>$trigger,'from_status'=>$from,'to_status'=>$to,
            'endpoint_id'=>$endpoint,'recipient'=>$recipient,'body'=>$body,
            'idempotency_key'=>'presta:' . Configuration::get(Settings::SITE_ID) . ':' . Settings::uuid(),
            'status'=>'PENDING','next_attempt_at'=>$now,'date_add'=>$now,'date_upd'=>$now,
        ]);
        if (!$ok) { throw new RuntimeException('Could not persist the Simply Connect SMS job.'); }
        return (int) Db::getInstance()->Insert_ID();
    }
    /** @return list<array<string,mixed>> */
    public function forOrder(int $orderId): array { $rows = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'simply_connect_sms` WHERE id_order=' . $orderId . ' ORDER BY id_simply_connect_sms DESC LIMIT 50'); return is_array($rows) ? $rows : []; }
    /** @return list<array<string,mixed>> */
    public function due(int $limit=25): array { $rows = Db::getInstance()->executeS("SELECT * FROM `" . _DB_PREFIX_ . "simply_connect_sms` WHERE status='PENDING' AND next_attempt_at <= UTC_TIMESTAMP() ORDER BY id_simply_connect_sms LIMIT " . (int)$limit); return is_array($rows) ? $rows : []; }
    public function claim(int $id): bool { return Db::getInstance()->execute("UPDATE `" . _DB_PREFIX_ . "simply_connect_sms` SET status='SENDING',locked_at=UTC_TIMESTAMP(),date_upd=UTC_TIMESTAMP() WHERE id_simply_connect_sms=" . $id . " AND (status='PENDING' OR (status='SENDING' AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)))") && Db::getInstance()->Affected_Rows() === 1; }
    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): void { $values['date_upd']=gmdate('Y-m-d H:i:s'); Db::getInstance()->update('simply_connect_sms',$values,'id_simply_connect_sms=' . $id); }
    /** @return list<array<string,mixed>> */
    public function awaitingSync(int $limit=50): array { $rows=Db::getInstance()->executeS("SELECT * FROM `"._DB_PREFIX_."simply_connect_sms` WHERE message_id IS NOT NULL AND status IN ('ACCEPTED','QUEUED','SENDING','UNKNOWN') ORDER BY date_upd LIMIT ".(int)$limit); return is_array($rows)?$rows:[]; }
}
