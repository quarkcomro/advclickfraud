<div class="panel">
    <div class="panel-heading">
        <i class="icon-shield"></i> {l s='Advanced Click Fraud Monitoring Dashboard' mod='advclickfraud'}
    </div>
    
    <!-- Row Statisici Globale -->
    <div class="row adv-stats-row">
        <div class="col-md-3">
            <div class="metric-card card-blue">
                <span class="metric-title">Total Click-uri Reclame</span>
                <span class="metric-value">{$stats.total_clicks|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-red">
                <span class="metric-title">Click-uri Frauduloase Detectate</span>
                <span class="metric-value">{$stats.total_fraud|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-orange">
                <span class="metric-title">Boți / Crawlers Identificați</span>
                <span class="metric-value">{$stats.bot_count|intval}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card card-green">
                <span class="metric-title">Timp Mediu pe Site (Secunde)</span>
                <span class="metric-value">{$stats.avg_duration|round}s</span>
            </div>
        </div>
    </div>
</div>

<!-- Configurare Reguli de Filtrare -->
<div class="panel">
    <div class="panel-heading"><i class="icon-cogs"></i> {l s='Setări Algoritm Detecție' mod='advclickfraud'}</div>
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
        <div class="form-group">
            <label class="control-label col-lg-3">Limită click-uri per IP</label>
            <div class="col-lg-3">
                <input type="number" name="ADVCLICKFRAUD_CLICK_LIMIT" value="{$click_limit|intval}" class="form-control" />
                <p class="help-block">Numărul maxim de click-uri dintr-o reclamă într-o fereastră temporală înainte de marcare ca fraudă.</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">Fereastră temporală de monitorizare (secunde)</label>
            <div class="col-lg-3">
                <input type="number" name="ADVCLICKFRAUD_TIME_WINDOW" value="{$time_window|intval}" class="form-control" />
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submit_adv_config" class="btn btn-default pull-right"><i class="process-icon-save"></i> Salvează</button>
        </div>
    </form>
</div>

<!-- Jurnal detaliat de analiză -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Jurnal detaliat în timp real al amenințărilor' mod='advclickfraud'}
    </div>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr class="nodrag nodrop">
                    <th><span class="title_box">Adresă IP</span></th>
                    <th><span class="title_box">Sursă/Campanie</span></th>
                    <th><span class="title_box">Număr Click-uri</span></th>
                    <th><span class="title_box">Timp pe site</span></th>
                    <th><span class="title_box">Interacțiune (Mouse / Taste)</span></th>
                    <th><span class="title_box">Rezoluție Ecran</span></th>
                    <th><span class="title_box">Scor Fraudă</span></th>
                    <th><span class="title_box">Tip Risc</span></th>
                    <th><span class="title_box">Ultima vizită</span></th>
                </tr>
            </thead>
            <tbody>
                {if $logs}
                    {foreach from=$logs item=log}
                        <tr>
                            <td><strong>{$log.ip_address|escape:'html':'UTF-8'}</strong></td>
                            <td><span class="label label-info">{$log.utm_source|escape:'html':'UTF-8'}</span></td>
                            <td><span class="badge">{$log.click_count|intval}</span></td>
                            <td>{$log.duration|intval} secunde</td>
                            <td>
                                <i class="icon-mouse-pointer"></i> {$log.mouse_movements|intval} M | 
                                <i class="icon-keyboard-o"></i> {$log.key_presses|intval} K
                            </td>
                            <td>{$log.screen_resolution|escape:'html':'UTF-8'}</td>
                            <td>
                                <div class="progress" style="margin-bottom:0;">
                                    <div class="progress-bar {if $log.fraud_score >= 70}progress-bar-danger{elif $log.fraud_score >= 40}progress-bar-warning{else}progress-bar-success{/if}" 
                                         role="progressbar" style="width: {$log.fraud_score|intval}%;">
                                        {$log.fraud_score|intval}%
                                    </div>
                                </div>
                            </td>
                            <td>
                                {if $log.fraud_score >= 70}
                                    <span class="label label-danger">Fraudă Critică (Blochează)</span>
                                {elif $log.is_bot}
                                    <span class="label label-warning">Bot Automatizat</span>
                                {else}
                                    <span class="label label-success">Utilizator Legitim</span>
                                {/if}
                            </td>
                            <td>{$log.date_upd|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="9" class="text-center">{l s='Nu s-au înregistrat click-uri suspecte din campanii publicitare.' mod='advclickfraud'}</td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>
