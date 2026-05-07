<style>
.newapi-token-card .token-toolbar .btn { border-radius: 6px; }
.newapi-token-card .token-panel { background: #f8fafc; border: 1px solid #eef1f5; border-radius: 10px; }
.newapi-token-card .token-panel code { display: block; color: #334155; font-size: 12px; }
.newapi-token-card .token-section-title { font-weight: 600; color: #334155; }
.newapi-token-card .token-metric { background: #f8fafc; border: 1px solid #eef1f5; border-radius: 10px; padding: 10px 12px; }
.newapi-token-card .token-metric .label { color: #94a3b8; font-size: 12px; margin-bottom: 2px; }
.newapi-token-card .token-metric .value { color: #1e293b; font-weight: 500; }
.newapi-token-card .progress { border-radius: 999px; background: #e5e7eb; }
.newapi-token-card .progress .progress-bar { border-radius: 999px; }
.newapi-token-toast { position: fixed; top: 20px; right: 20px; z-index: 1080; min-width: 220px; max-width: 340px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12); display: none; }
.newapi-token-toast.show { display: block; }
.newapi-token-toast .toast-inner { display: flex; align-items: center; gap: 8px; padding: 10px 12px; }
.newapi-token-toast .toast-icon { color: #16a34a; }
.newapi-token-toast .toast-text { color: #334155; font-size: 13px; }
</style>

<div class="card mb-3 shadow-sm newapi-token-card">
    <div class="card-body p-3 p-md-4">
        {if empty($Token.key)}
        <button class="btn btn-light border"><small class="text-primary" onclick="location.reload()">服务繁忙，请刷新重试</small></button>
        {else/}
        <div class="d-flex align-items-center token-toolbar" style="gap:0.5em;margin-bottom:0.75em;">
            <button type="button" class="btn rsb btn-primary btn-sm" id="orderFlowBtn"
                onclick="renew($(this), '{$Think.get.id}')">续费</button>
            <button type="button" class="btn rsb btn-outline-primary btn-sm" id="orderFlowBtn"
                onclick="orderFlow($(this), '{$Think.get.id}')">订购流量</button>
            <button class="btn rsb btn-danger btn-sm" data-toggle="modal" data-target=".cancelrequire">停用</button>
        </div>
        {/if}

        <div class="token-panel p-2 p-md-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">API 地址</small>
                <button class="btn btn-sm btn-link p-0" onclick="copyText('{$ApiBaseUrl}')"><i class="fa fa-copy"></i></button>
            </div>
            <code class="text-break">{$ApiBaseUrl}</code>
        </div>

        <div class="token-panel p-2 p-md-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">API Key (<small>{$Token.name}</small>)</small>
                <button class="btn btn-sm btn-link p-0" onclick="copyText('{$Token.key}')"><i class="fa fa-copy"></i></button>
            </div>
            <code class="text-break">{$Token.key}</code>
        </div>

        <div class="mb-3 d-flex align-items-center flex-wrap" style="gap:0.4rem;">
            {switch name="Token.status"}
            {case value="1"}<span class="badge bg-success">正常</span>{/case}
            {case value="2"}<span class="badge bg-secondary">已禁用</span>{/case}
            {case value="4"}<span class="badge bg-warning">额度用尽</span>{/case}
            {default /}<span class="badge bg-danger">异常</span>
            {/switch}
            {php}
            $group = $Token['group'] ?? 'default';
            $groupInfo = isset($Groups[$group]) ? $Groups[$group] : ['desc' => $group, 'ratio' => 1];
            echo '<span class="badge bg-light text-secondary border"><i class="fa fa-tag me-1"></i>' . htmlspecialchars($groupInfo['desc']) . '</span>';
            echo '<span class="badge bg-info ms-1">' . $groupInfo['ratio'] . 'x 倍率</span>';
            {/php}
        </div>

        <div class="border-top pt-3">
            <div class="d-flex justify-content-between mb-2 align-items-center">
                <span class="token-section-title"><i class="fa fa-database text-primary me-1 mr-1"></i>额度</span>
                {if condition="$Token.unlimited_quota"}
                <span class="badge bg-info">无限</span>
                {else /}
                <small class="text-muted">{$Token.used_quota} / {$Token.remain_quota}</small>
                {/if}
            </div>
            {if condition="!$Token.unlimited_quota"}
            <div class="progress" style="height:8px" data-used="{$Token.used_quota}" data-remain="{$Token.remain_quota}"><div class="progress-bar bg-success"></div></div>
            <div class="d-flex justify-content-between mt-1"><small class="text-success">剩余 {$Token.remain_quota}</small><small class="text-muted">已用 {$Token.used_quota}</small></div>
            {/if}
        </div>

        <div class="border-top pt-3 mt-3">
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <div class="token-metric">
                        <div class="label">过期时间</div>
                        <div class="value">
                            {if condition="$Token.expired_time == -1"}
                            永不过期
                            {else /}
                            {:date('Y-m-d H:i', $Token['expired_time'])}
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="token-metric">
                        <div class="label">创建时间</div>
                        <div class="value">{:date('Y-m-d H:i', $Token['created_time'])}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="newapi-token-toast" id="copyToast" role="status" aria-live="polite">
    <div class="toast-inner">
        <i class="fa fa-check-circle toast-icon"></i>
        <span class="toast-text" id="copyToastMsg">已复制</span>
    </div>
</div>
<script>
function showCopyToast(msg){
    var toast = $('#copyToast');
    $('#copyToastMsg').text(msg || '已复制');
    toast.stop(true, true).addClass('show').fadeIn(120);
    clearTimeout(window.__copyToastTimer);
    window.__copyToastTimer = setTimeout(function(){
        toast.fadeOut(180, function(){ toast.removeClass('show'); });
    }, 1400);
}

function copyText(t){
    if(navigator.clipboard){
        navigator.clipboard.writeText(t).then(function(){ showCopyToast('已复制'); }).catch(function(){ showCopyToast('复制失败，请手动复制'); });
    }else{
        $('<textarea>').val(t).appendTo('body').select();
        var ok = document.execCommand('copy');
        $('textarea').remove();
        showCopyToast(ok ? '已复制' : '复制失败，请手动复制');
    }
}
$('.progress').each(function(){var u=$(this).data('used'),r=$(this).data('remain'),t=u+r;if(t>0){var p=Math.round(u/t*100);$(this).find('.progress-bar').css('width',p+'%')}});
</script>

