<div class="card mt-2">
  <h3 class="card-header">{l s='Simply Connect SMS' d='Modules.Simplyconnectsms.Admin'}</h3>
  <div class="card-body">
    {$sc_notice nofilter}
    <form method="post">
      <input type="hidden" name="sc_order_id" value="{$sc_order_id|intval}">
      <input type="hidden" name="sc_token" value="{$sc_token|escape:'htmlall':'UTF-8'}">
      <div class="form-group"><label>{l s='Message' d='Modules.Simplyconnectsms.Admin'}</label><textarea class="form-control" name="sc_sms_body" rows="3" maxlength="1000" required></textarea></div>
      <button class="btn btn-primary" name="submitSimplyConnectManualSms" type="submit">{l s='Queue SMS' d='Modules.Simplyconnectsms.Admin'}</button>
    </form>
    <hr><strong>{l s='Recent messages' d='Modules.Simplyconnectsms.Admin'}</strong>
    {if empty($sc_history)}<p>{l s='No messages for this order.' d='Modules.Simplyconnectsms.Admin'}</p>{else}<ul class="list-unstyled mt-2">{foreach $sc_history as $item}<li class="mb-2"><code>{$item.status|escape:'htmlall':'UTF-8'}</code> {$item.recipient|escape:'htmlall':'UTF-8'}<br><small>{$item.date_add|escape:'htmlall':'UTF-8'}</small><br>{$item.body|escape:'htmlall':'UTF-8'}{if $item.error_message}<br><span class="text-danger">{$item.error_message|escape:'htmlall':'UTF-8'}</span>{/if}</li>{/foreach}</ul>{/if}
  </div>
</div>
