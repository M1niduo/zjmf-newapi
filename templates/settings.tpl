<style>
.token-settings-card { width: 100%; max-width: 100%; border: 1px solid #eef1f5; border-radius: 12px; overflow: hidden; }
.token-settings-card .card-header { border-bottom: 1px solid #eef1f5; }
.token-settings-card .form-select,
.token-settings-card .form-control { width: 100%; }
.token-settings-card select[multiple] option { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.token-settings-card .setting-item { padding: 12px; border: 1px solid #eef1f5; border-radius: 10px; background: #f8fafc; }
.token-settings-card .setting-label { font-size: 13px; color: #475569; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.token-settings-card .setting-tip { font-size: 12px; color: #94a3b8; margin-top: 6px; }
.newapi-toast { position: fixed; top: 20px; right: 20px; z-index: 1080; min-width: 260px; max-width: 360px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12); display: none; }
.newapi-toast.show { display: block; }
.newapi-toast .toast-inner { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; }
.newapi-toast .toast-icon { font-size: 18px; line-height: 1.2; margin-top: 1px; }
.newapi-toast .toast-text { color: #334155; font-size: 13px; word-break: break-word; }
.newapi-toast.success .toast-icon { color: #16a34a; }
.newapi-toast.error .toast-icon { color: #dc2626; }
</style>
<div class="card shadow-sm token-settings-card">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fa fa-cog text-primary me-2"></i>令牌配置</h6>
    </div>
    <div class="card-body p-3 p-md-4">
        <form id="tokenForm" style="width:100%">
            <input type="hidden" name="id" value="{$Token.id}">

            <div class="row g-3">
                <div class="col-12">
                    <div class="setting-item">
                        <div class="setting-label"><i class="fa fa-layer-group"></i>分组</div>
                        <select class="form-select" name="group" id="group" style="width:100%">
                            {foreach $Groups as $key => $g}
                            <option value="{$key}" {if condition="$Token.group == $key"}selected{/if}>{$g.desc} ({$g.ratio}x)</option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="setting-item">
                        <div class="setting-label"><i class="fa fa-exchange-alt"></i>跨组重试</div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="cross_group_retry" id="crossRetry" value="1" {if condition='$Token.cross_group_retry'}checked{/if}>
                            <label class="form-check-label" for="crossRetry">启用后请求失败自动尝试其他分组</label>
                        </div>
                    </div>
                </div>

                <!--<div class="col-12">
                    <div class="setting-item">
                        <div class="setting-label"><i class="fa fa-robot"></i>模型限制</div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="model_limits_enabled" id="modelLimit" value="1" {if condition='$Token.model_limits_enabled'}checked{/if}>
                            <label class="form-check-label" for="modelLimit">启用模型限制</label>
                        </div>
                        <select class="form-select" name="model_limits" id="models" multiple style="height:180px;width:100%" {if condition='!$Token.model_limits_enabled'}disabled{/if}>
                            {volist name="Models" id="m"}
                            <option value="{$m}" {if condition="strpos($Token.model_limits, $m) !== false"}selected{/if}>{$m}</option>
                            {/volist}
                        </select>
                        <div class="setting-tip">按住 Ctrl 或 Shift 多选模型</div>
                    </div>
                </div>-->

                <div class="col-12">
                    <div class="setting-item">
                        <div class="setting-label"><i class="fa fa-shield-alt"></i>IP白名单</div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="ipLimit" {if condition='$Token.allow_ips'}checked{/if}>
                            <label class="form-check-label" for="ipLimit">启用IP白名单</label>
                        </div>
                        <textarea class="form-control" name="allow_ips" id="ips" rows="3" style="width:100%" placeholder="每行一个IP地址，例如：&#10;192.168.1.1&#10;10.0.0.0/24" {if condition='!$Token.allow_ips'}disabled{/if}>{$Token.allow_ips}</textarea>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()"><i class="fa fa-undo me-1"></i>重置</button>
                <button type="submit" class="btn btn-primary btn-sm" id="submitBtn"><i class="fa fa-save me-1"></i>保存配置</button>
            </div>
        </form>
    </div>
</div>
<div class="newapi-toast" id="resultToast" role="status" aria-live="polite">
    <div class="toast-inner">
        <i class="fa fa-check-circle toast-icon" id="resultToastIcon"></i>
        <div class="toast-text" id="resultToastMsg">保存成功</div>
    </div>
</div>
<script>
$(function(){
    $('#modelLimit').change(function(){$('#models').prop('disabled',!this.checked)});
    $('#ipLimit').change(function(){
        $('#ips').prop('disabled',!this.checked);
        if(!this.checked) $('#ips').val('');
    });
    
    $('#tokenForm').on('submit', function(e){
        e.preventDefault();
        var btn = $('#submitBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>保存中...');
        
        var models = [];
        $('#models option:selected').each(function(){ models.push($(this).val()) });
        
        var params = {
            action: 'save_token',
            model_id: '{$Token.id}',
            group: $('#group').val(),
            model_limits_enabled: $('#modelLimit').prop('checked') ? 1 : 0,
            model_limits: models.join(','),
            cross_group_retry: $('#crossRetry').prop('checked') ? 1 : 0,
            allow_ips: $('#ipLimit').prop('checked') ? $('#ips').val() : ''
        };
        $.ajax({
            url: '/provision/custom/content?id={$Detail.host_data.hostid}&key=info',
            method: 'GET',
            data: params,
            success: function(res){
                btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i>保存配置');
                if(typeof res === 'string') res = JSON.parse(res);
                showModal(res.success !== false, res.message || '保存成功');
            },
            error: function(){
                showModal(false, '请求失败，请稍后重试');
                btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i>保存配置');
            },
            complete: function(){
                btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i>保存配置');
            }
        });
    });
});

function showModal(success, msg){
    var toast = $('#resultToast');
    var icon = $('#resultToastIcon');

    toast.stop(true, true).removeClass('success error').addClass(success ? 'success' : 'error');
    icon.removeClass('fa-check-circle fa-times-circle').addClass(success ? 'fa-check-circle' : 'fa-times-circle');
    $('#resultToastMsg').text(msg);

    toast.addClass('show').fadeIn(120);
    clearTimeout(window.__newapiToastTimer);
    window.__newapiToastTimer = setTimeout(function(){
        toast.fadeOut(180, function(){ toast.removeClass('show'); });
    }, 1800);
}

function resetForm(){
    $('#tokenForm')[0].reset();
    $('#models').prop('disabled', !$('#modelLimit').prop('checked'));
    $('#ips').prop('disabled', !$('#ipLimit').prop('checked'));
}
</script>
