{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">{Lang::T('Paystack Payment Gateway')}</div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/paystack">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Public Key')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_public_key" name="paystack_public_key" value="{$paystack_public_key}">
                            <p class="help-block">{Lang::T('Get your API keys from your Paystack Dashboard')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Secret Key')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_secret_key" name="paystack_secret_key" value="{$paystack_secret_key}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Webhook Secret')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_webhook_secret" name="paystack_webhook_secret" value="{$paystack_webhook_secret}">
                            <p class="help-block">{Lang::T('Used to verify webhook requests from Paystack')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Success URL')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_success_url" name="paystack_success_url" value="{$paystack_success_url}">
                            <p class="help-block">{Lang::T('Redirect URL after successful payment')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Failed URL')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="paystack_failed_url" name="paystack_failed_url" value="{$paystack_failed_url}">
                            <p class="help-block">{Lang::T('Redirect URL after failed payment')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Webhook URL')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" readonly value="{$_url}callback/paystack">
                            <p class="help-block">{Lang::T('Add this URL to your Paystack Dashboard > Settings > Webhooks')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary" type="submit" name="save" value="paystack">{Lang::T('Save Changes')}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}