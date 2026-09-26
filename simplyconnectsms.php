<?php

if (!defined('_PS_VERSION_')) { exit; }

$releaseAutoload = __DIR__ . '/autoload.php';
$developmentAutoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($releaseAutoload)) { require_once $releaseAutoload; }
elseif (is_readable($developmentAutoload)) { require_once $developmentAutoload; }

use SimplyConnect\PrestaShop\Api\ApiClient;
use SimplyConnect\PrestaShop\Api\CurlTransport;
use SimplyConnect\PrestaShop\Outbox;
use SimplyConnect\PrestaShop\Settings;
use SimplyConnect\PrestaShop\TemplateRenderer;
use SimplyConnect\PrestaShop\Worker;

final class Simplyconnectsms extends Module {
    public function __construct() {
        $this->name='simplyconnectsms'; $this->tab='advertising_marketing'; $this->version='0.1.0'; $this->author='Simply Connect';
        $this->need_instance=0; $this->bootstrap=true; $this->ps_versions_compliancy=['min'=>'8.1.0','max'=>_PS_VERSION_];
        parent::__construct();
        $this->displayName=$this->trans('Simply Connect SMS',[], 'Modules.Simplyconnectsms.Admin');
        $this->description=$this->trans('Send transactional order SMS through your Simply Connect gateways.',[], 'Modules.Simplyconnectsms.Admin');
        $this->confirmUninstall=$this->trans('Remove settings and local SMS history?',[], 'Modules.Simplyconnectsms.Admin');
    }

    public function install(): bool {
        return parent::install() && Outbox::install() && $this->registerHook('actionOrderStatusPostUpdate') && $this->registerHook('displayAdminOrderSideBottom') && $this->registerHook('displayAdminOrderMainBottom') && $this->registerHook('actionCronJob') && $this->initialize();
    }
    private function initialize(): bool { Settings::initialize(); return true; }
    public function uninstall(): bool { $ok=Outbox::uninstall(); Settings::clear(); return $ok && parent::uninstall(); }

    public function getContent(): string {
        $output=''; $settings=new Settings();
        if (Tools::isSubmit('submitSimplyConnectSms')) {
            try {
                $statuses=array_map('intval',(array)Tools::getValue('SC_SMS_STATUSES',[])); $templates=[];
                foreach (OrderState::getOrderStates((int)$this->context->language->id) as $state) { $templates[(string)$state['id_order_state']]=(string)Tools::getValue('SC_SMS_TEMPLATE_'.$state['id_order_state'],''); }
                $settings->save(trim((string)Tools::getValue('SC_SMS_API_KEY','')),trim((string)Tools::getValue('SC_SMS_ENDPOINT','')),$statuses,$templates);
                $output.=$this->displayConfirmation($this->trans('Settings saved.',[], 'Modules.Simplyconnectsms.Admin'));
            } catch (Throwable $e) { $output.=$this->displayError($e->getMessage()); }
        }
        if (Tools::isSubmit('refreshSimplyConnectSms')) {
            try { $settings->saveEndpoints($this->client()->endpoints()); $output.=$this->displayConfirmation($this->trans('Endpoints refreshed.',[], 'Modules.Simplyconnectsms.Admin')); }
            catch (Throwable $e) { $output.=$this->displayError($e->getMessage()); }
        }
        return $output.$this->renderForm($settings);
    }

    private function renderForm(Settings $settings): string {
        $states=OrderState::getOrderStates((int)$this->context->language->id); $stateOptions=[];
        foreach ($states as $state) { $stateOptions[]=['id'=>(int)$state['id_order_state'],'name'=>$state['name']]; }
        $endpointOptions=[]; foreach ($settings->endpoints() as $endpoint) { if ($endpoint['enabled']) { $endpointOptions[]=['id'=>$endpoint['id'],'name'=>$endpoint['name'].($endpoint['phoneNumber']?' — '.$endpoint['phoneNumber']:'')]; } }
        $inputs=[
            ['type'=>'password','label'=>$this->trans('API key',[], 'Modules.Simplyconnectsms.Admin'),'name'=>'SC_SMS_API_KEY','desc'=>$this->trans('Leave blank to preserve the encrypted key. You can also define SIMPLY_CONNECT_API_KEY.',[], 'Modules.Simplyconnectsms.Admin')],
            ['type'=>'select','label'=>$this->trans('SMS endpoint',[], 'Modules.Simplyconnectsms.Admin'),'name'=>'SC_SMS_ENDPOINT','options'=>['query'=>$endpointOptions,'id'=>'id','name'=>'name']],
            ['type'=>'select','label'=>$this->trans('Automatic statuses',[], 'Modules.Simplyconnectsms.Admin'),'name'=>'SC_SMS_STATUSES[]','multiple'=>true,'options'=>['query'=>$stateOptions,'id'=>'id','name'=>'name'],'desc'=>$this->trans('Hold Ctrl/Cmd to select multiple statuses.',[], 'Modules.Simplyconnectsms.Admin')],
        ];
        foreach ($states as $state) { $inputs[]=['type'=>'textarea','label'=>$state['name'],'name'=>'SC_SMS_TEMPLATE_'.$state['id_order_state'],'rows'=>2,'cols'=>60]; }
        $helper=new HelperForm(); $helper->module=$this; $helper->name_controller=$this->name; $helper->token=Tools::getAdminTokenLite('AdminModules'); $helper->currentIndex=AdminController::$currentIndex.'&configure='.$this->name; $helper->submit_action='submitSimplyConnectSms'; $helper->show_toolbar=false;
        $helper->fields_value=['SC_SMS_ENDPOINT'=>$settings->endpointId(),'SC_SMS_STATUSES[]'=>$settings->statuses()];
        foreach ($states as $state) { $helper->fields_value['SC_SMS_TEMPLATE_'.$state['id_order_state']]=$settings->template((int)$state['id_order_state']); }
        $cron=$this->context->link->getModuleLink($this->name,'cron',['token'=>$settings->cronToken()]);
        $intro='<div class="alert alert-info">'.$this->trans('API keys need SMS_SEND and MESSAGES_READ. Run the cron URL every minute:',[], 'Modules.Simplyconnectsms.Admin').' <code>'.htmlspecialchars($cron,ENT_QUOTES,'UTF-8').'</code></div>';
        $refresh='<form method="post"><button class="btn btn-default" name="refreshSimplyConnectSms" type="submit">'.$this->trans('Refresh endpoints',[], 'Modules.Simplyconnectsms.Admin').'</button></form>';
        return $intro.$refresh.$helper->generateForm([['form'=>['legend'=>['title'=>$this->displayName,'icon'=>'icon-envelope'],'input'=>$inputs,'submit'=>['title'=>$this->trans('Save',[], 'Admin.Actions')]]]]);
    }

    /** @param array<string,mixed> $params */
    public function hookActionOrderStatusPostUpdate(array $params): void {
        $state=$params['newOrderStatus']??null; $orderId=(int)($params['id_order']??0); if (!$state instanceof OrderState || !in_array((int)$state->id,(new Settings())->statuses(),true)) { return; }
        try { $this->queueOrder($orderId,'status',isset($params['oldOrderStatus']->id)?(int)$params['oldOrderStatus']->id:null,(int)$state->id,(new Settings())->template((int)$state->id)); } catch (Throwable $e) { PrestaShopLogger::addLog('Simply Connect SMS: '.$e->getMessage(),3); }
    }

    /** @param array<string,mixed> $params */
    public function hookDisplayAdminOrderSideBottom(array $params): string { return $this->renderOrderPanel($params); }
    /** @param array<string,mixed> $params */
    public function hookDisplayAdminOrderMainBottom(array $params): string { return $this->renderOrderPanel($params); }
    private function renderOrderPanel(array $params): string {
        $orderId=(int)($params['id_order']??$params['id']??Tools::getValue('id_order',0)); if ($orderId<=0) { return ''; }
        $notice='';
        if (Tools::isSubmit('submitSimplyConnectManualSms') && (int)Tools::getValue('sc_order_id')===$orderId && hash_equals(Tools::getAdminTokenLite('AdminOrders'), (string) Tools::getValue('sc_token', ''))) {
            try { $this->queueOrder($orderId,'manual',null,(int)(new Order($orderId))->current_state,trim((string)Tools::getValue('sc_sms_body'))); $notice=$this->displayConfirmation($this->trans('SMS queued.',[], 'Modules.Simplyconnectsms.Admin')); }
            catch (Throwable $e) { $notice=$this->displayError($e->getMessage()); }
        }
        $this->context->smarty->assign(['sc_order_id'=>$orderId,'sc_token'=>Tools::getAdminTokenLite('AdminOrders'),'sc_history'=>(new Outbox())->forOrder($orderId),'sc_notice'=>$notice]);
        return $this->display(__FILE__,'views/templates/hook/order_panel.tpl');
    }

    private function queueOrder(int $orderId,string $trigger,?int $from,?int $to,string $template): int {
        $order=new Order($orderId); if (!Validate::isLoadedObject($order)) { throw new RuntimeException('Order not found.'); }
        $address=new Address((int)$order->id_address_delivery); $customer=new Customer((int)$order->id_customer); $state=$to?new OrderState($to,(int)$order->id_lang):null;
        $recipient=preg_replace('/[^0-9+]/','',(string)($address->phone_mobile?:$address->phone))??''; $endpoint=(new Settings())->endpointId();
        if ($endpoint===''||$recipient===''||trim($template)==='') { throw new RuntimeException('Endpoint, customer phone and SMS body are required.'); }
        $body=(new TemplateRenderer())->render($template,['order_reference'=>$order->reference,'customer_first_name'=>$customer->firstname,'customer_last_name'=>$customer->lastname,'order_total'=>Tools::displayPrice((float)$order->total_paid,(int)$order->id_currency),'order_status'=>$state instanceof OrderState?$state->name:'','shop_name'=>Configuration::get('PS_SHOP_NAME')]);
        return (new Outbox())->enqueue($orderId,$trigger,$from,$to,$endpoint,$recipient,$body);
    }

    public function hookActionCronJob(): void { try { (new Worker(new Outbox(),$this->client()))->run(); } catch (Throwable $e) { PrestaShopLogger::addLog('Simply Connect SMS worker: '.$e->getMessage(),3); } }
    public function runWorker(): array { return (new Worker(new Outbox(),$this->client()))->run(); }
    private function client(): ApiClient { $settings=new Settings(); return new ApiClient(new CurlTransport(),$settings->baseUrl(),$settings->apiKey()); }
}
