<style>
.token-logs-filter .form-control,
.token-logs-filter .form-select { min-width: 140px; }
.token-logs-filter .btn { white-space: nowrap; }
</style>
<link rel="stylesheet" href="/plugins/servers/newapi/templates/assets/bootstrap-table.min.css">
<script src="/plugins/servers/newapi/templates/assets/bootstrap-table.min.js"></script>
<script src="/plugins/servers/newapi/templates/assets/bootstrap-table-zh-CN.js"></script>
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fa fa-list-alt text-primary mr-1"></i>Token 使用日志</h6>
    <small class="text-muted">按时间倒序</small>
  </div>
  <div class="card-body">
    <div id="toolbar" class="token-logs-filter mb-3">
      <div class="d-flex flex-wrap align-items-end" style="gap:8px;">
        <div>
          <label class="form-label mb-1 small text-muted">开始时间</label>
          <input type="datetime-local" class="form-control form-control-sm" id="filterStartTime">
        </div>
        <div>
          <label class="form-label mb-1 small text-muted">结束时间</label>
          <input type="datetime-local" class="form-control form-control-sm" id="filterEndTime">
        </div>
        <div>
          <button type="button" class="btn btn-primary btn-sm" id="btnSearchLogs"><i class="fa fa-search mr-1"></i>查询</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetLogs"><i class="fa fa-undo mr-1"></i>重置</button>
        </div>
      </div>
    </div>

    <table
      id="tokenLogsTable"
      class="table table-bordered table-hover"
      data-toggle="table"
      data-side-pagination="server"
      data-pagination="true"
      data-page-size="5"
      data-page-list="[5,10]"
      data-search="false"
      data-show-refresh="true"
      data-show-columns="true"
      data-toolbar="#toolbar">
    </table>
  </div>
</div>

<script>
(function () {
  var tokenName = {:json_encode($Token['name'] ?? '')};

  function formatTime(ts) {
    if (!ts) return '-';
    var d = new Date(ts * 1000);
    var pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' +
           pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }

  function safeJsonParse(s) {
    try { return JSON.parse(s || '{}'); } catch (e) { return {}; }
  }

  function toTimestamp(value, fallback) {
    if (!value) return fallback;
    var t = Math.floor(new Date(value).getTime() / 1000);
    return isNaN(t) ? fallback : t;
  }

  function toDatetimeLocal(ts) {
    var d = new Date(ts * 1000);
    var pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' +
           pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function setDefaultTimeRange() {
    var now = new Date();
    var start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0);
    $('#filterStartTime').val(toDatetimeLocal(Math.floor(start.getTime() / 1000)));
    $('#filterEndTime').val(toDatetimeLocal(Math.floor(now.getTime() / 1000)));
  }

  setDefaultTimeRange();

  $('#tokenLogsTable').bootstrapTable({
    method: 'get',
    url: '/provision/custom/content?id={$Detail.host_data.hostid}&key=logs&action=logs',
    queryParams: function (params) {
      var defaultStart = Math.floor(new Date(new Date().toDateString()).getTime() / 1000);
      var defaultEnd = Math.floor(Date.now() / 1000);

      return {
        p: Math.floor(params.offset / params.limit) + 1,
        page_size: params.limit,
        type: 2,
        token_name: $('#filterTokenName').val() || tokenName,
        model_name: $('#filterModelName').val() || '',
        start_timestamp: toTimestamp($('#filterStartTime').val(), defaultStart),
        end_timestamp: toTimestamp($('#filterEndTime').val(), defaultEnd),
        group: $('#filterGroup').val() || ''
      };
    },
    responseHandler: function (res) {
      if (!res || res.success !== true || !res.data) {
        return { total: 0, rows: [] };
      }
      return {
        total: res.data.total || 0,
        rows: res.data.items || []
      };
    },
    columns: [
      { field: 'id', title: '#ID', align: 'center', width: 70 },
      { field: 'created_at', title: '时间', formatter: function (v) { return formatTime(v); }, width: 170 },
      { field: 'model_name', title: '模型' },
      { field: 'quota', title: '额度消耗', align: 'right' },
      { field: 'prompt_tokens', title: 'Prompt', align: 'right' },
      { field: 'completion_tokens', title: 'Completion', align: 'right' },
      { field: 'use_time', title: '耗时(ms)', align: 'right' },
      {
        field: 'other',
        title: '附加信息',
        formatter: function (v) {
          var other = safeJsonParse(v);
          var path = other.request_path || '-';
          var frt = other.frt != null ? other.frt : '-';
          var cache = other.cache_tokens != null ? other.cache_tokens : '-';
          return '路径: ' + path + '<br>FRT: ' + frt + ' ms<br>Cache: ' + cache;
        }
      }
    ]
  });

  $('#btnSearchLogs').on('click', function () {
    $('#tokenLogsTable').bootstrapTable('refresh', {pageNumber: 1});
  });

  $('#btnResetLogs').on('click', function () {
    $('#filterTokenName').val(tokenName);
    $('#filterModelName').val('');
    $('#filterGroup').val('');
    setDefaultTimeRange();
    $('#tokenLogsTable').bootstrapTable('selectPage', 1);
  });
})();
</script>