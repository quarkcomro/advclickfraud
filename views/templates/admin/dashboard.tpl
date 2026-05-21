<!-- SYSTEM VERSION BAR HEADER -->
<div class="panel" style="margin-bottom: 15px; padding: 10px 20px;">
    <div class="row" style="line-height: 24px;">
        <div class="col-md-6">
            <h3 style="margin: 0; font-size: 16px; font-weight: bold; color: #363a41;">
                <i class="icon-shield" style="color: #2eacd5;"></i> Advanced Click Fraud & Anti-Scraping Matrix
            </h3>
        </div>
        <div class="col-md-6 text-right">
            <span class="label label-info" style="font-size: 11px; font-weight: bold; padding: 4px 8px;">PrestaShop 9 Engine Layer</span>
            <span class="label label-default" style="font-size: 11px; font-weight: bold; padding: 4px 8px; background-color: #555;">v1.6.0</span>
        </div>
    </div>
</div>

<!-- NATIVE PRESTASHOP TABS NAVIGATION -->
<ul class="nav nav-tabs" id="acf-dashboard-tabs" style="margin-bottom: 20px;">
    <li class="active">
        <a href="#tab-analytics" data-toggle="tab"><i class="icon-bar-chart"></i> {l s='Analytics & Threat Logs' mod='advclickfraud'}</a>
    </li>
    <li>
        <a href="#tab-settings" data-toggle="tab"><i class="icon-cogs"></i> {l s='Detection Settings' mod='advclickfraud'}</a>
    </li>
    <li>
        <a href="#tab-whitelist" data-toggle="tab"><i class="icon-bookmark"></i> {l s='Whitelist Exception Rules' mod='advclickfraud'}</a>
    </li>
</ul>

<div class="tab-content" style="border: none; padding: 0; background: transparent;">
    
    <!-- ==================== TAB 1: OPERATIONAL ANALYTICS ==================== -->
    <div class="tab-pane active" id="tab-analytics">
        <!-- Global Statistics Widgets Block -->
        <div class="row adv-stats-row" style="margin-bottom: 20px;">
            <div class="col-md-3">
                <div class="metric-card card-blue" data-toggle="tooltip" title="{l s='Total click counter recorded from multi-channel paid ads containing operational tracking IDs.' mod='advclickfraud'}">
                    <span class="metric-title">{l s='Total Ad Clicks' mod='advclickfraud'}</span>
                    <span class="metric-value">{$stats.total_clicks|intval}</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card-red" data-toggle="tooltip" title="{l s='Total unique network IPs that reached or crossed your current configured Export Threshold percentage score.' mod='advclickfraud'}">
                    <span class="metric-title">{l s='Critical Threats' mod='advclickfraud'}</span>
                    <span class="metric-value">{$stats.total_fraud|intval}</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card-orange" data-toggle="tooltip" title="{l s='Identified digital signatures or high frequency catalog indexers matching bot scraping telemetry rules.' mod='advclickfraud'}">
                    <span class="metric-title">{l s='Bots & Scrapers' mod='advclickfraud'}</span>
                    <span class="metric-value">{$stats.bot_count|intval}</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card-green" data-toggle="tooltip" title="{l s='The global average retention duration computed dynamically from all active database trackers tracking records.' mod='advclickfraud'}">
                    <span class="metric-title">{l s='Avg Visit Duration' mod='advclickfraud'}</span>
                    <span class="metric-value">{$stats.avg_duration|round}s</span>
                </div>
            </div>
        </div>

        <!-- Threats Logging Data Table Block (Expanded to 100% full width inside Tab 1) -->
        <div class="panel">
            <div class="panel-heading"><i class="icon-list"></i> {l s='Detailed Live Threat Log Journal' mod='advclickfraud'}</div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th><a href="{$sort_url}&order_by=ip_address&order_way={$next_order_way}">{l s='IP Address' mod='advclickfraud'} {if $order_by=='ip_address'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=device_fingerprint&order_way={$next_order_way}">{l s='Device Hardware ID' mod='advclickfraud'} {if $order_by=='device_fingerprint'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=utm_source&order_way={$next_order_way}">{l s='Traffic Channel' mod='advclickfraud'} {if $order_by=='utm_source'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=click_count&order_way={$next_order_way}">{l s='Paid Ad Clicks' mod='advclickfraud'} {if $order_by=='click_count'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=total_pages_visited&order_way={$next_order_way}">{l s='Catalog Views / hr' mod='advclickfraud'} {if $order_by=='total_pages_visited'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=duration&order_way={$next_order_way}">{l s='Session Time' mod='advclickfraud'} {if $order_by=='duration'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th><a href="{$sort_url}&order_by=mouse_movements&order_way={$next_order_way}">{l s='Device Engagement' mod='advclickfraud'} {if $order_by=='mouse_movements'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th>{l s='Resolution' mod='advclickfraud'}</th>
                            <th><a href="{$sort_url}&order_by=fraud_score&order_way={$next_order_way}">{l s='Fraud Matrix' mod='advclickfraud'} {if $order_by=='fraud_score'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                            <th>{l s='Threat Classification' mod='advclickfraud'}</th>
                            <th><a href="{$sort_url}&order_by=date_upd&order_way={$next_order_way}">{l s='Telemetry Timestamp' mod='advclickfraud'} {if $order_by=='date_upd'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $logs}
                            {foreach from=$logs item=log}
                                <tr>
                                    <td><strong>{$log.ip_address|escape:'html':'UTF-8'}</strong></td>
                                    <td>
                                        {if $log.device_fingerprint}
                                            <span class="label label-default" data-toggle="tooltip" title="Absolute Fingerprint Hash: {$log.device_fingerprint|escape:'html':'UTF-8'}" style="font-family: monospace; background-color: #5a626a; color: #fff;">
                                                {$log.device_fingerprint|truncate:12:'...':true|escape:'html':'UTF-8'}
                                            </span>
                                        {else}
                                            <span class="text-muted" style="font-style: italic;">{l s='Pending telemetry' mod='advclickfraud'}</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $log.utm_source == 'Google Ads'}
                                            <span class="label label-primary" style="background-color: #4285f4;"><i class="icon-google"></i> Google Ads</span>
                                        {elseif $log.utm_source == 'Meta Ads'}
                                            <span class="label label-info" style="background-color: #3b5998;"><i class="icon-facebook"></i> Meta Ads</span>
                                        {elseif $log.utm_source == 'TikTok Ads'}
                                            <span class="label label-inverse" style="background-color: #000; color: #fff;"><i class="icon-play-sign"></i> TikTok Ads</span>
                                        {elseif $log.utm_source}
                                            <span class="label label-warning" style="background-color: #ec971f;">{$log.utm_source|escape:'html':'UTF-8'}</span>
                                        {else}
                                            <span class="label label-default" style="background-color: #777;">Direct / Organic</span>
                                        {/if}
                                    </td>
                                    <td><span class="badge" style="font-weight: bold;">{$log.click_count|intval}</span></td>
                                    <td><span class="badge badge-warning" style="background-color: #f0ad4e;">{$log.total_pages_visited|intval} {l s='pages' mod='advclickfraud'}</span></td>
                                    <td>{$log.duration|intval}s</td>
                                    <td>{$log.mouse_movements|intval} M | {$log.key_presses|intval} K</td>
                                    <td>{$log.screen_resolution|escape:'html':'UTF-8'}</td>
                                    <td>
                                        <div class="progress" style="margin-bottom:0; height: 16px; border-radius: 2px;">
                                            <div class="progress-bar {if $log.fraud_score >= 70}progress-bar-danger{elseif $log.fraud_score >= 40}progress-bar-warning{else}progress-bar-success{/if}" style="width: {$log.fraud_score|intval}%; line-height: 16px; font-weight: bold;">
                                                {$log.fraud_score|intval}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {if $log.is_scraper}
                                            <span class="label label-danger" style="background-color: #d9534f;">Price Scraper Loop</span>
                                        {elseif $log.fraud_score >= 70}
                                            <span class="label label-danger" style="background-color: #d9534f;">Critical Evasion Fraud</span>
                                        {elseif $log.is_bot}
                                            <span class="label label-warning" style="background-color: #f0ad4e;">Automated Bot</span>
                                        {else}
                                            <span class="label label-success" style="background-color: #5cb85c;">Verified Legitimate</span>
                                        {/if}
                                    </td>
                                    <td>{$log.date_upd|escape:'html':'UTF-8'}</td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr><td colspan="11" class="text-center" style="padding: 20px; font-size: 13px; font-style: italic; color: #999;">{l s='No malicious threat records detected inside active database logs.' mod='advclickfraud'}</td></tr>
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

        <!-- Automation Script Integration Endpoint Block -->
        <div class="panel">
          <div class="panel-heading"><i class="icon-link"></i> {l s='Google Ads Live Synchronizer Integration & Scripts Automation' mod='advclickfraud'}</div>
          <div class="alert alert-info" style="margin: 5px 0 0 0;">
              <p style="font-weight: bold; margin-bottom: 5px;"><i class="icon-info-sign"></i> Text Automated Export Engine URL:</p>
              <p>{l s='Map this address token directly inside your hourly Google Ads background optimization manager script scripts configurations layer:' mod='advclickfraud'}</p>
              <code style="font-size: 13px; padding: 10px; display: block; word-break: break-all; background: #fff; border: 1px solid #bce8f1; color: #31708f; font-family: monospace;">{$export_link}</code>
          </div>
        </div>
    </div>
    
    <!-- ==================== TAB 2: DETECTION CONFIGURATIONS ==================== -->
    <div class="tab-pane" id="tab-settings">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> {l s='Fine-Tune Detection Algorithms (No Hardcoding)' mod='advclickfraud'}
            </div>
            
            <form action="{$form_action|escape:'html':'UTF-8'}" method="post" id="acf_main_config_form" class="form-horizontal">
                <div class="row">
                    <!-- Column 1: Thresholds & Timing Limits Matrix -->
                    <div class="col-md-6" style="border-right: 1px solid #edf1f2; padding-right: 25px;">
                        <h4><i class="icon-dashboard"></i> {l s='Click Fraud & Behavioral Limits' mod='advclickfraud'}</h4>
                        <br/>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Maximum allowed clicks from the same IP inside the time window before incrementing score.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Click Limit / IP' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_CLICK_LIMIT" value="{$click_limit|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='The historical evaluation timeframe window calculated back in seconds.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Time Window (seconds)' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_TIME_WINDOW" value="{$time_window|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Shorter session duration threshold in seconds considered suspicious if no interactions occur.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Min Inactivity (seconds)' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_MIN_DURATION" value="{$min_duration|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Maximum inactivity bounding ceiling threshold to safely exclude slow users reading descriptions.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Max Inactivity (seconds)' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_MAX_DURATION" value="{$max_duration|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Maximum unique product profile pages an IP can browse inside a single minute block.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Product Views Limit / min' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_SCRAPE_LIMIT" value="{$scrape_limit|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='The dynamic fraud score minimum percentage required to export an IP to the blocklist.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Export Threshold Score' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_EXPORT_THRESHOLD" value="{$export_threshold|intval}" class="form-control" min="10" max="100"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Automated table optimization system retention period. Truncates older metrics.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Database History Retention (Days)' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_RETENTION_DAYS" value="{$retention_days|intval}" class="form-control"/>
                            </div>
                        </div>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Maximum data table lines rendered simultaneously inside the administration log list.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Rows per page in table' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <input type="number" name="ADVCLICKFRAUD_DISPLAY_LIMIT" value="{$display_limit|intval}" class="form-control"/>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column 2: Advanced Network Infrastructure & Dynamic Geofencing Mapping -->
                    <div class="col-md-6" style="padding-left: 25px;">
                        <h4><i class="icon-globe"></i> {l s='Network Verification & Targeted Markets' mod='advclickfraud'}</h4>
                        <br/>
                        <div class="form-group" data-toggle="tooltip" title="{l s='Enable this if your store routes traffic via Cloudflare. Uses secure headers to identify real proxy network source IPs and locations.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='Cloudflare Proxy Headers' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <span class="switch prestashop-switch fixed-width-lg">
                                    <input type="radio" name="ADVCLICKFRAUD_CLOUDFLARE_ACTIVE" id="cf_active_on" value="1" {if $cloudflare_active == 1}checked="checked"{/if} />
                                    <label for="cf_active_on">{l s='Yes' mod='advclickfraud'}</label>
                                    <input type="radio" name="ADVCLICKFRAUD_CLOUDFLARE_ACTIVE" id="cf_active_off" value="0" {if $cloudflare_active == 0}checked="checked"{/if} />
                                    <label for="cf_active_off">{l s='No' mod='advclickfraud'}</label>
                                    <a class="slide-button btn"></a>
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group" data-toggle="tooltip" title="{l s='Enable this to run high-speed binary lookups using a local MaxMind database to scan for commercial data-centers, VPNs, and proxies networks.' mod='advclickfraud'}">
                            <label class="control-label col-lg-4">{l s='MaxMind Local DB Scan' mod='advclickfraud'}</label>
                            <div class="col-lg-8">
                                <span class="switch prestashop-switch fixed-width-lg">
                                    <input type="radio" name="ADVCLICKFRAUD_MAXMIND_ACTIVE" id="mm_active_on" value="1" {if $maxmind_active == 1}checked="checked"{/if} />
                                    <label for="mm_active_on">{l s='Yes' mod='advclickfraud'}</label>
                                    <input type="radio" name="ADVCLICKFRAUD_MAXMIND_ACTIVE" id="mm_active_off" value="0" {if $maxmind_active == 0}checked="checked"{/if} />
                                    <label for="mm_active_off">{l s='No' mod='advclickfraud'}</label>
                                    <a class="slide-button btn"></a>
                                </span>
                            </div>
                        </div>
                        
                        <!-- NEW EDITABLE GEOFENCING COUNTRY GRID INTERFACE BLOCK -->
                        <div class="form-group" style="margin-top: 25px;">
                            <label class="control-label col-lg-4" style="text-align: left; font-weight: bold;">
                                <i class="icon-flag"></i> {l s='Geofencing Allowed Countries Selection' mod='advclickfraud'}
                            </label>
                            <div class="col-lg-8">
                                <p class="help-block" style="font-size: 11px; margin-bottom: 12px; color: #737a82;">
                                    {l s='Select the countries where your advertising campaigns are running. Traffic origins outside this grid will trigger immediate fraud metric adjustments.' mod='advclickfraud'}
                                </p>
                                <div style="max-height: 230px; overflow-y: auto; border: 1px solid #ced4da; padding: 12px; border-radius: 4px; background-color: #fafbfc;">
                                    {if $system_countries}
                                        {foreach from=$system_countries item=country}
                                            {assign var="iso" value=$country.iso_code|strtoupper}
                                            <div class="checkbox" style="margin-bottom: 6px;">
                                                <label style="font-weight: normal; cursor: pointer;">
                                                    <input type="checkbox" name="ADVCLICKFRAUD_GEOTAGS[]" value="{$iso|escape:'html':'UTF-8'}" {if in_array($iso, $active_geotags)}checked="checked"{/if} />
                                                    <span style="font-family: monospace; font-weight: bold; background: #e2e6ea; padding: 1px 4px; border-radius: 2px; margin-right: 5px;">{$iso}</span> 
                                                    {$country.name|escape:'html':'UTF-8'}
                                                </label>
                                            </div>
                                        {/foreach}
                                    {else}
                                        <p class="text-danger">{l s='Error: Could not retrieve system countries lists context.' mod='advclickfraud'}</p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="panel-footer" style="overflow: hidden; margin-top: 15px;">
                <form action="{$form_action|escape:'html':'UTF-8'}" method="post" style="display: inline-block;" onsubmit="return confirm('{l s='Are you sure you want to completely erase all tracking logs and start metrics from zero?' mod='advclickfraud'}');">
                    <button type="submit" name="submit_reset_stats" class="btn btn-danger">
                        <i class="icon-refresh"></i> {l s='Reset Analytics Statistics' mod='advclickfraud'}
                    </button>
                </form>
                <button type="submit" name="submit_adv_config" class="btn btn-default pull-right" form="acf_main_config_form">
                    <i class="process-icon-save"></i> {l s='Save Fine Settings' mod='advclickfraud'}
                </button>
            </div>
        </div>
    </div>
    
    <!-- ==================== TAB 3: WHITELIST MANAGEMENT ==================== -->
    <div class="tab-pane" id="tab-whitelist">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-bookmark"></i> {l s='IP & CIDR Whitelist Exception Manager' mod='advclickfraud'}
            </div>
            <div class="row">
                <!-- Add rule mini sub-panel context layout wrapper form -->
                <div class="col-md-4" style="border-right: 1px solid #edf1f2; padding-right: 20px;">
                    <h4>{l s='Add New Exception Entry Rule' mod='advclickfraud'}</h4>
                    <br/>
                    <form action="{$form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal" style="padding: 10px;">
                        <div class="form-group">
                            <label style="font-weight: bold;">{l s='IP Address or CIDR Block Subnet Range' mod='advclickfraud'}</label>
                            <input type="text" name="acf_whitelist_ip" class="form-control" placeholder="e.g., 192.168.1.5 or 66.249.64.0/19" required />
                        </div>
                        <div class="form-group">
                            <label style="font-weight: bold;">{l s='Description Notes / Reference Assignment' mod='advclickfraud'}</label>
                            <input type="text" name="acf_whitelist_desc" class="form-control" placeholder="e.g., Office Dedicated Fiber Connection or Bing Crawler Spiders" required />
                        </div>
                        <br/>
                        <button type="submit" name="submit_add_whitelist" class="btn btn-primary btn-block" style="padding: 8px; font-weight: bold;">
                            <i class="icon-plus-sign"></i> {l s='Add Exception to Whitelist Table' mod='advclickfraud'}
                        </button>
                    </form>
                </div>
                
                <!-- Whitelist data log records output visualization lists table matrix -->
                <div class="col-md-8" style="padding-left: 20px;">
                    <h4>{l s='Active Safe Exception Framework Database Rules List' mod='advclickfraud'}</h4>
                    <br/>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{l s='IP / Subnet CIDR Block' mod='advclickfraud'}</th>
                                    <th>{l s='Description / Notes Context Reference' mod='advclickfraud'}</th>
                                    <th>{l s='Created Timestamp' mod='advclickfraud'}</th>
                                    <th class="text-center">{l s='Action' mod='advclickfraud'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {if $whitelist_items}
                                    {foreach from=$whitelist_items item=w_item}
                                        <tr>
                                            <td><code style="font-size: 13px; font-weight: bold;">{$w_item.ip_or_cidr|escape:'html':'UTF-8'}</code></td>
                                            <td><strong>{$w_item.description|escape:'html':'UTF-8'}</strong></td>
                                            <td>{$w_item.date_add|escape:'html':'UTF-8'}</td>
                                            <td class="text-center">
                                                <a href="{$form_action}&delete_whitelist={$w_item.id_whitelist|intval}" class="btn btn-xs btn-danger" onclick="return confirm('{l s='Completely remove this exception routing rule?' mod='advclickfraud'}');" style="padding: 2px 8px;">
                                                    <i class="icon-trash"></i> {l s='Delete' mod='advclickfraud'}
                                                </a>
                                            </td>
                                        </tr>
                                    {/foreach}
                                {* Fallback notice layout row mapping *}
                                {else}
                                    <tr><td colspan="4" class="text-center" style="padding: 15px; font-style: italic; color: #999;">{l s='No custom whitelist bypass criteria records generated inside infrastructure layer.' mod='advclickfraud'}</td></tr>
                                {/if}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COMPACT STATE MEMORY CONTROLLER SCRIPT TRIGGER -->
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    // Harness browser storage namespaces to persist active administrative tabs positions across refresh events
    var localStorageKey = "acf_active_dashboard_tab";
    var savedTabHash = localStorage.getItem(localStorageKey);
    
    if (savedTabHash) {
        var targetTabTrigger = document.querySelector('#acf-dashboard-tabs a[href="' + savedTabHash + '"]');
        if (targetTabTrigger) {
            // Native jQuery Bootstrap interface navigation method bindings executed manually
            if (typeof window.jQuery !== "undefined") {
                window.jQuery(targetTabTrigger).tab('show');
            }
        }
    }
    
    // Bind active click trackers onto elements anchors grid arrays loops
    var tabAnchors = document.querySelectorAll('#acf-dashboard-tabs a[data-toggle="tab"]');
    tabAnchors.forEach(function(anchor) {
        anchor.addEventListener('shown.bs.tab', function(e) {
            var activeHash = e.target.getAttribute('href');
            localStorage.setItem(localStorageKey, activeHash);
        });
    });
});
</script>
