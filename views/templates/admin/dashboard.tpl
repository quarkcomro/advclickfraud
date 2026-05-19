{* Advanced Click Fraud Dashboard Template - 100% English Translation Ready *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-shield"></i> {l s='Advanced Click Fraud & Scraper Monitoring Dashboard' mod='advclickfraud'}
    </div>
    
    <div class="row adv-stats-row">
        <div class="col-md-3"><div class="metric-card card-blue"><span class="metric-title">{l s='Total Ad Clicks' mod='advclickfraud'}</span><span class="metric-value">{\$stats.total_clicks|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-red"><span class="metric-title">{l s='Critical Threats' mod='advclickfraud'}</span><span class="metric-value">{\$stats.total_fraud|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-orange"><span class="metric-title">{l s='Bots & Scrapers' mod='advclickfraud'}</span><span class="metric-value">{\$stats.bot_count|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-green"><span class="metric-title">{l s='Avg Visit Duration' mod='advclickfraud'}</span><span class="metric-value">{\$stats.avg_duration|round}s</span></div></div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-cogs"></i> {l s='Fine-Tune Detection Algorithms' mod='advclickfraud'}</div>
    <form action="{\$form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='Click Fraud Protection Settings' mod='advclickfraud'}</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Click Limit / IP' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_CLICK_LIMIT" value="{\$click_limit|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Time Window (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_TIME_WINDOW" value="{\$time_window|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Min Idle Check (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_MIN_DURATION" value="{\$min_duration|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Max Idle Check (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_MAX_DURATION" value="{\$max_duration|intval}" class="form-control"/></div>
                </div>
            </div>
            <div class="col-md-6">
                <h4>{l s='Scrapers & Database Optimization' mod='advclickfraud'}</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Product Views / hour' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_SCRAPE_LIMIT" value="{\$scrape_limit|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Data Retention (Days)' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_RETENTION_DAYS" value="{\$retention_days|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Rows per Page' mod='advclickfraud'}</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_DISPLAY_LIMIT" value="{\$display_limit|intval}" class="form-control"/></div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submit_adv_config" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save Global Settings' mod='advclickfraud'}</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-link"></i> {l s='Google Ads Automation Sync API' mod='advclickfraud'}</div>
    <div class="alert alert-info">
        <p>{l s='Use the secure URL export line in your Google Ads Hourly Sync Scripts to pull real-time IPs marked with Critical Fraud:' mod='advclickfraud'}</p>
        <code style="font-size: 13px; padding: 8px; display: block; word-break: break-all;">{\$export_link}</code>
    </div>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-list"></i> {l s='Real-Time Threat Intelligence Logs' mod='advclickfraud'}</div>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th><a href="{\(sort_url}&order_by=ip_address&order_way={\)next_order_way}">{l s='IP Address' mod='advclickfraud'} {if \(order_by=='ip_address'}{if\)order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{\(sort_url}&order_by=utm_source&order_way={\)next_order_way}">{l s='Traffic Campaign' mod='advclickfraud'} {if \(order_by=='utm_source'}{if\)order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{\(sort_url}&order_by=click_count&order_way={\)next_order_way}">{l s='Ads Clicks' mod='advclickfraud'} {if \(order_by=='click_count'}{if\)order_way=='ASC'}<i class="icon-caret-up">
