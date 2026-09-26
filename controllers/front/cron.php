<?php

use SimplyConnect\PrestaShop\Settings;

final class SimplyconnectsmsCronModuleFrontController extends ModuleFrontController {
    public $auth=false; public $ajax=true;
    public function display(): void {
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        $provided=(string)Tools::getValue('token',''); $expected=(new Settings())->cronToken();
        if ($expected==='' || !hash_equals($expected,$provided)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); return; }
        try { echo json_encode($this->module->runWorker(),JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>'worker_failed']); }
    }
}
