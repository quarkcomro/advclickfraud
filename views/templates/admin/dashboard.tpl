<div class="panel">
    <div class="panel-heading">
        <i class="icon-shield"></i> {l s='Advanced Click Fraud & Scraper Monitoring Dashboard' mod='advclickfraud'}
    </div>
    
    <!-- Global Statistics Widgets Block -->
    <div class="row adv-stats-row">
        <div class="col-md-3">
            <div class="metric-card card-blue">
                <span class="metric-title">{l s='Total Ad Clicks' mod='advclickfraud'}</span>
                <span class="metric-value">{$stats.total_clicks|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-red">
                <span class="metric-title">{l s='Critical Threats' mod='advclickfraud'}</span>
                <span class="metric-value">{$stats.total_fraud|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-orange">
                <span class="metric-title">{l s='Bots & Scrapers' mod='advclickfraud'}</span>
                <span class="metric-value">{$stats.bot_count|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-green">
                <span class="metric-title">{l s='Avg Visit Duration' mod='advclickfraud'}</span>
                <span class="metric-value">{$stats.avg_duration|round}s</span>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Algorithms Fine-Tuning Administration Form -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Fine-Tune Detection Algorithms (No Hardcoding)' mod='advclickfraud'}
    </div>
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='Click Fraud Settings' mod='advclickfraud'}</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Click Limit / IP' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_CLICK_LIMIT" value="{$click_limit|intval}" class="form-control"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Time Window (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_TIME_WINDOW" value="{$time_window|intval}" class="form-control"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Min Inactivity (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_MIN_DURATION" value="{$min_duration|intval}" class="form-control"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Max Inactivity (seconds)' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_MAX_DURATION" value="{$max_duration|intval}" class="form-control"/>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h4>{l s='Scrapers & Data Retention Settings' mod='advclickfraud'}</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Product Views Limit / min' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_SCRAPE_LIMIT" value="{$scrape_limit|intval}" class="form-control"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Database History Retention (Days)' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_RETENTION_DAYS" value="{$retention_days|intval}" class="form-control"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">{l s='Rows per page in table' mod='advclickfraud'}</label>
                    <div class="col-lg-8">
                        <input type="number" name="ADVCLICKFRAUD_DISPLAY_LIMIT" value="{$display_limit|intval}" class="form-control"/>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submit_adv_config" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save Fine Settings' mod='advclickfraud'}
            </button>
        </div>
    </form>
</div>

<!-- Automation Script Integration Endpoint Block -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-link"></i> {l s='Google Ads Integration & Automation' mod='advclickfraud'}
    </div>
    <div class="alert alert-info">
        <p>{l s='Text Export URL for the hourly Google Ads script:' mod='advclickfraud'}</p>
        <code style="font-size: 13px; padding: 8px; display: block; word-break: break-all;">{$export_link}</code>
    </div>
</div>

<!-- Threats Logging Data Table Block -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Detailed threat log' mod='advclickfraud'}
    </div>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th><a href="{$sort_url}&order_by=ip_address&order_way={$next_order_way}">{l s='IP Address' mod='advclickfraud'} {if $order_by=='ip_address'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=utm_source&order_way={$next_order_way}">{l s='Campaign' mod='advclickfraud'} {if $order_by=='utm_source'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=click_count&order_way={$next_order_way}">{l s='Ads Clicks' mod='advclickfraud'} {if $order_by=='click_count'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=total_pages_visited&order_way={$next_order_way}">{l s='Visited pages / hour' mod='advclickfraud'} {if $order_by=='total_pages_visited'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=duration&order_way={$next_order_way}">{l s='Site time' mod='advclickfraud'} {if $order_by=='duration'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=mouse_movements&order_way={$next_order_way}">{l s='Interaction Mouse' mod='advclickfraud'} {if $order_by=='mouse_movements'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th>{l s='Resolution' mod='advclickfraud'}</th>
                    <th><a href="{$sort_url}&order_by=fraud_score&order_way={$next_order_way}">{l s='Fraud Score' mod='advclickfraud'} {if $order_by=='fraud_score'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th>{l s='Risk Type' mod='advclickfraud'}</th>
                    <th><a href="{$sort_url}&order_by=date_upd&order_way={$next_order_way}">{l s='Last visit' mod='advclickfraud'} {if $order_by=='date_upd'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                </tr>
            </thead>
            <tbody>
                {if $logs}
                    {foreach from=$logs item=log}
                        <tr>
                            <td><strong>{$log.ip_address|escape:'html':'UTF-8'}</strong></td>
                            <td><span class="label label-info">{if $log.utm_source}{$log.utm_source|escape:'html':'UTF-8'}{else}Direct/Organic{/if}</span></td>
                            <td><span class="badge">{$log.click_count|intval}</span></td>
                            <td><span class="badge badge-warning" style="background-color: #f0ad4e;">{$log.total_pages_visited|intval} {l s='pages' mod='advclickfraud'}</span></td>
                            <td>{$log.duration|intval}s</td>
                            <td>{$log.mouse_movements|intval} M | {$log.key_presses|intval} K</td>
                            <td>{$log.screen_resolution|escape:'html':'UTF-8'}</td>
                            <td>
                                <div class="progress" style="margin-bottom:0;">
                                    <div class="progress-bar {if $log.fraud_score >= 70}progress-bar-danger{elseif $log.fraud_score >= 40}progress-bar-warning{else}progress-bar-success{/if}" style="width: {$log.fraud_score|intval}%;">
                                        {$log.fraud_score|intval}%
                                    </div>
                                </div>
                            </td>
                            <td>
                                {if $log.is_scraper}
                                    <span class="label label-danger">{l s='Price Scraper Blocks' mod='advclickfraud'}</span>
                                {elseif $log.fraud_score >= 70}
                                    <span class="label label-danger">{l s='Critical Fraud' mod='advclickfraud'}</span>
                                {elseif $log.is_bot}
                                    <span class="label label-warning">{l s='Automated Bot' mod='advclickfraud'}</span>
                                {else}
                                    <span class="label label-success">{l s='Legitimate User' mod='advclickfraud'}</span>
                                {/if}
                            </td>
                            <td>{$log.date_upd|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                {else}
                    <tr><td colspan="10" class="text-center">{l s='No threats detected.' mod='advclickfraud'}</td></tr>
                {/if}
            </tbody>
        </table>
    </div>

    <!-- UI Bootstrap Pagination Interface Wrapper -->
    {if $total_pages > 1}
        <div class="panel-footer" style="background: #fff; border-top: 1px solid #edf1f2; padding: 15px 0;">
            <div class="row">
                <div class="col-sm-6 text-left" style="line-height: 34px; padding-left: 15px;">
                    {l s='Page' mod='advclickfraud'} <strong>{$current_page}</strong> {l s='of' mod='advclickfraud'} <strong>{$total_pages}</strong>
                </div>
                <div class="col-sm-6 text-right" style="padding-right: 15px;">
                    <ul class="pagination" style="margin: 0;">
                        <li class="{if $current_page <= 1}disabled{/if}">
                            <a href="{if $current_page > 1}{$page_url}&page={$current_page - 1}{else}#{/if}"><i class="icon-chevron-left"></i> {l s='Back' mod='advclickfraud'}</a>
                        </li>
                        
                        {for $p=1 to $total_pages}
                            <li class="{if $current_page == $p}active{/if}">
                                <a href="{$page_url}&page={$p}">{$p}</a>
                            </li>
                        {/for}
                        
                        <li class="{if $current_page >= $total_pages}disabled{/if}">
                            <a href="{if $current_page < $total_pages}{$page_url}&page={$current_page + 1}{else}#{/if}">{l s='Next' mod='advclickfraud'} <i class="icon-chevron-right"></i></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    {/if}
</div>
