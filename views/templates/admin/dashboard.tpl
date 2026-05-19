<div class="panel">
    <div class="panel-heading">
        <i class="icon-shield"></i> {l s='Advanced Click Fraud & Scraper Monitoring Dashboard' mod='advclickfraud'}
    </div>
    
    <div class="row adv-stats-row">
        <div class="col-md-3"><div class="metric-card card-blue"><span class="metric-title">Total Click-uri Reclame</span><span class="metric-value">{$stats.total_clicks|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-red"><span class="metric-title">Amenințări Critice</span><span class="metric-value">{$stats.total_fraud|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-orange"><span class="metric-title">Boți & Scraperi</span><span class="metric-value">{$stats.bot_count|intval}</span></div></div>
        <div class="col-md-3"><div class="metric-card card-green"><span class="metric-title">Timp Mediu Vizită</span><span class="metric-value">{$stats.avg_duration|round}s</span></div></div>
    </div>
</div>

<!-- FORMULAR AJUSTĂRI FINE ALGORITM -->
<div class="panel">
    <div class="panel-heading"><i class="icon-cogs"></i> {l s='Configurare Fină Algoritmi Detecție (Fără Hardcoding)' mod='advclickfraud'}</div>
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <div class="row">
            <div class="col-md-6">
                <h4>Setări Click Fraud</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">Limită Click-uri / IP</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_CLICK_LIMIT" value="{$click_limit|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">Fereastră Timp (secunde)</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_TIME_WINDOW" value="{$time_window|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">Inactivitate Minimă (secunde)</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_MIN_DURATION" value="{$min_duration|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">Inactivitate Maximă (secunde)</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_MAX_DURATION" value="{$max_duration|intval}" class="form-control"/></div>
                </div>
            </div>
            <div class="col-md-6">
                <h4>Setări Scraperi & Păstrare Date</h4>
                <div class="form-group">
                    <label class="control-label col-lg-4">Limită Vizualizare Produse / min</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_SCRAPE_LIMIT" value="{$scrape_limit|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">Păstrare Istoric Bază Date (Zile)</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_RETENTION_DAYS" value="{$retention_days|intval}" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-4">Rânduri pe pagină în tabel</label>
                    <div class="col-lg-8"><input type="number" name="ADVCLICKFRAUD_DISPLAY_LIMIT" value="{$display_limit|intval}" class="form-control"/></div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submit_adv_config" class="btn btn-default pull-right"><i class="process-icon-save"></i> Salvează Setările Fine</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-link"></i> {l s='Integrare și Automatizare Google Ads' mod='advclickfraud'}</div>
    <div class="alert alert-info">
        <p>URL Export text pentru scriptul orar din Google Ads:</p>
        <code style="font-size: 13px; padding: 8px; display: block; word-break: break-all;">{$export_link}</code>
    </div>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-list"></i> {l s='Jurnal detaliat amenințări' mod='advclickfraud'}</div>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th><a href="{$sort_url}&order_by=ip_address&order_way={$next_order_way}">Adresă IP {if $order_by=='ip_address'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=utm_source&order_way={$next_order_way}">Campanie {if $order_by=='utm_source'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=click_count&order_way={$next_order_way}">Click-uri Ads {if $order_by=='click_count'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=total_pages_visited&order_way={$next_order_way}">Pagini vizitate / oră {if $order_by=='total_pages_visited'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=duration&order_way={$next_order_way}">Timp site {if $order_by=='duration'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th><a href="{$sort_url}&order_by=mouse_movements&order_way={$next_order_way}">Interacțiune Mouse {if $order_by=='mouse_movements'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th>Rezoluție</th>
                    <th><a href="{$sort_url}&order_by=fraud_score&order_way={$next_order_way}">Scor Fraudă {if $order_by=='fraud_score'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                    <th>Tip Risc</th>
                    <th><a href="{$sort_url}&order_by=date_upd&order_way={$next_order_way}">Ultima vizită {if $order_by=='date_upd'}{if $order_way=='ASC'}<i class="icon-caret-up"></i>{else}<i class="icon-caret-down"></i>{/if}{/if}</a></th>
                </tr>
            </thead>

            <tbody>
                {if $logs}
                    {foreach from=$logs item=log}
                        <tr>
                            <td><strong>{$log.ip_address|escape:'html':'UTF-8'}</strong></td>
                            <td><span class="label label-info">{if $log.utm_source}{$log.utm_source|escape:'html':'UTF-8'}{else}Direct/Scraper{/if}</span></td>
                            <td><span class="badge">{$log.click_count|intval}</span></td>
                            <td>{$log.duration|intval}s</td>
                            <td>{$log.mouse_movements|intval} M | {$log.key_presses|intval} K</td>
                            <td>{$log.screen_resolution|escape:'html':'UTF-8'}</td>
                            <td>
                                <div class="progress" style="margin-bottom:0;">
                                    <div class="progress-bar {if $log.fraud_score >= 70}progress-bar-danger{elseif $log.fraud_score >= 40}progress-bar-warning{else}progress-bar-success{/if}" style="width: {$log.fraud_score|intval}%;">{$log.fraud_score|intval}%</div>
                                </div>
                            </td>
                            <td>
                                {if $log.is_scraper}
                                    <span class="label label-danger">Scraper Prețuri Blochează</span>
                                {elseif $log.fraud_score >= 70}
                                    <span class="label label-danger">Fraudă Critică</span>
                                {elseif $log.is_bot}
                                    <span class="label label-warning">Bot Automatizat</span>
                                {else}
                                    <span class="label label-success">Utilizator Legitim</span>
                                {/if}
                            </td>
                            <td>{$log.date_upd|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                {else}
                    <tr><td colspan="9" class="text-center">Nu s-au detectat amenințări.</td></tr>
                {/if}
            </tbody>
        </table>
    </div>
            <!-- SISTEM PAGINAȚIE COMPATIBIL BOOTSTRAP PRESTASHOP -->
        {if $total_pages > 1}
            <div class="panel-footer" style="background: #fff; border-top: 1px solid #edf1f2; padding: 15px 0;">
                <div class="row">
                    <div class="col-sm-6 text-left" style="line-height: 34px; padding-left: 15px;">
                        Pagina <strong>{$current_page}</strong> din <strong>{$total_pages}</strong>
                    </div>
                    <div class="col-sm-6 text-right" style="padding-right: 15px;">
                        <ul class="pagination" style="margin: 0;">
                            <li class="{if $current_page <= 1}disabled{/if}">
                                <a href="{if $current_page > 1}{$page_url}&page={$current_page - 1}{else}#{/if}"><i class="icon-chevron-left"></i> Înapoi</a>
                            </li>
                            
                            {for $p=1 to $total_pages}
                                <li class="{if $current_page == $p}active{/if}">
                                    <a href="{$page_url}&page={$p}">{$p}</a>
                                </li>
                            {/for}
                            
                            <li class="{if $current_page >= $total_pages}disabled{/if}">
                                <a href="{if $current_page < $total_pages}{$page_url}&page={$current_page + 1}{else}#{/if}">Înainte <i class="icon-chevron-right"></i></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>
