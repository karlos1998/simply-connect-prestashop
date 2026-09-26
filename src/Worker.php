<?php

namespace SimplyConnect\PrestaShop;

use SimplyConnect\PrestaShop\Api\ApiClient;
use SimplyConnect\PrestaShop\Api\ApiException;
use SimplyConnect\PrestaShop\Api\UnknownOutcomeException;
use Throwable;

final class Worker {
    public function __construct(private readonly Outbox $outbox, private readonly ApiClient $client) {}
    /** @return array{processed:int,synced:int} */
    public function run(): array {
        $processed=0;
        foreach ($this->outbox->due() as $job) {
            $id=(int)$job['id_simply_connect_sms']; if (!$this->outbox->claim($id)) { continue; } ++$processed;
            try {
                $r=$this->client->sendSms((string)$job['endpoint_id'],(string)$job['recipient'],(string)$job['body'],(string)$job['idempotency_key']);
                $this->outbox->update($id,['status'=>$r['status'],'message_id'=>$r['messageId'],'dispatch_id'=>$r['dispatchId'],'command_id'=>$r['commandId'],'correlation_id'=>$r['correlationId'],'locked_at'=>null]);
            } catch (UnknownOutcomeException $e) {
                $this->outbox->update($id,['status'=>'UNKNOWN','error_kind'=>'unknown_outcome','error_message'=>$e->getMessage(),'locked_at'=>null]);
            } catch (ApiException $e) {
                $attempt=(int)$job['attempts']+1;
                if (in_array($e->kind,['rate_limit','server'],true) && $attempt<=5) {
                    $delay=$e->retryAfter ?? min(3600,60*(2**($attempt-1)));
                    $this->outbox->update($id,['status'=>'PENDING','attempts'=>$attempt,'next_attempt_at'=>gmdate('Y-m-d H:i:s',time()+$delay),'error_kind'=>$e->kind,'error_message'=>$e->getMessage(),'retry_after'=>$delay,'locked_at'=>null]);
                } else { $this->outbox->update($id,['status'=>'FAILED','attempts'=>$attempt,'error_kind'=>$e->kind,'error_message'=>$e->getMessage(),'problem_code'=>$e->problemCode,'correlation_id'=>$e->correlationId,'retry_after'=>$e->retryAfter,'locked_at'=>null]); }
            } catch (Throwable $e) { $this->outbox->update($id,['status'=>'FAILED','error_kind'=>'client','error_message'=>$e->getMessage(),'locked_at'=>null]); }
        }
        $synced=0;
        foreach ($this->outbox->awaitingSync() as $job) {
            try { $m=$this->client->message((string)$job['message_id']); if (is_string($m['status']??null)) { $this->outbox->update((int)$job['id_simply_connect_sms'],['status'=>$m['status'],'sync_attempts'=>(int)$job['sync_attempts']+1]); ++$synced; } } catch (Throwable) { $this->outbox->update((int)$job['id_simply_connect_sms'],['sync_attempts'=>(int)$job['sync_attempts']+1]); }
        }
        return compact('processed','synced');
    }
}
