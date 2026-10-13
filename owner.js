// RSA Meat Trading - Owner dashboard
(function () {
  'use strict';

  var API = '../api/owner-report.php';
  var PALETTE = ['#e60024', '#ff7a8a', '#e67300', '#f2a65a', '#6b6b6b', '#c9c9c9'];
  var charts = {};
  var report = null;

  function $(id) { return document.getElementById(id); }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function peso(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function kg(n) {
    return Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 2 });
  }
  function pctText(p) {
    return (p > 0 ? '+' : '') + p.toFixed(1) + '%';
  }

  /* ---------- load ---------- */
  async function load() {
    var month = $('monthPicker').value;
    if (!month) return;
    var basis = $('basisPicker').value;
    $('reportError').style.display = 'none';
    $('reportSub').textContent = 'Loading…';

    try {
      var res = await fetch(API + '?month=' + encodeURIComponent(month) + '&basis=' + encodeURIComponent(basis) + '&_=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (res.status === 401) { window.location.href = 'index.php'; return; }
      var data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || 'Unable to load the sales report.');
      report = data;
      render();
    } catch (err) {
      $('reportError').textContent = err.message;
      $('reportError').style.display = 'block';
      $('reportSub').textContent = '';
    }
  }

  /* ---------- render ---------- */
  function render() {
    var d = report;
    var s = d.summary;
    var basisText = d.basis === 'completed' ? 'Completed orders only' : 'All orders';

    $('reportTitle').textContent = 'Sales Report — ' + d.label;
    $('reportSub').textContent = basisText + ' · figures use the order date';
    document.querySelectorAll('.month-name').forEach(function (el) { el.textContent = d.label; });

    $('kpiSales').textContent = peso(s.sales);
    $('kpiOrders').textContent = s.orders;
    $('kpiAvg').textContent = peso(s.avg_order);
    $('kpiUnits').textContent = kg(s.units);

    var chg = $('kpiChange');
    chg.className = 'kpi-sub';
    if (s.change_pct === null) {
      chg.textContent = 'No sales last month to compare';
    } else {
      chg.textContent = (s.change_pct >= 0 ? '▲ ' : '▼ ') + pctText(s.change_pct) + ' vs last month (' + peso(s.prev_sales) + ')';
      chg.className = 'kpi-sub ' + (s.change_pct >= 0 ? 'up' : 'down');
    }

    renderTrendChart(d.trend);
    renderDailyChart(d.daily);
    renderProductCharts();
    renderBestTable();
    renderMonthlyTable(d.trend);
  }

  function getRanked() {
    var by = $('rankPicker').value === 'sales' ? 'sales' : 'qty';
    return report.products.slice().sort(function (a, b) {
      return (b[by] - a[by]) || (b.sales - a.sales);
    });
  }

  /* ---------- charts ---------- */
  function chartsReady() { return typeof window.Chart !== 'undefined'; }

  function setChartMessage(canvasId, text) {
    var box = $(canvasId).parentNode;
    var msg = box.querySelector('.chart-msg');
    if (!text) { if (msg) msg.remove(); return; }
    if (!msg) {
      msg = document.createElement('div');
      msg.className = 'chart-msg';
      box.appendChild(msg);
    }
    msg.textContent = text;
  }

  function drawChart(key, canvasId, config, isEmpty, emptyText) {
    if (charts[key]) { charts[key].destroy(); delete charts[key]; }
    if (!chartsReady()) {
      setChartMessage(canvasId, 'Charts could not load. Check your internet connection and refresh.');
      return;
    }
    if (isEmpty) { setChartMessage(canvasId, emptyText); return; }
    setChartMessage(canvasId, '');
    charts[key] = new Chart($(canvasId), config);
  }

  var moneyTick = function (v) { return '₱' + Number(v).toLocaleString('en-PH'); };

  function renderTrendChart(trend) {
    var empty = trend.every(function (t) { return t.sales === 0; });
    drawChart('trend', 'trendChart', {
      type: 'bar',
      data: {
        labels: trend.map(function (t) { return t.label; }),
        datasets: [{
          label: 'Sales',
          data: trend.map(function (t) { return t.sales; }),
          backgroundColor: trend.map(function (t, i) { return i === trend.length - 1 ? '#e60024' : '#f4a6b0'; }),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + peso(c.parsed.y); } } } },
        scales: { y: { beginAtZero: true, ticks: { callback: moneyTick } } }
      }
    }, empty, 'No sales recorded in the last 12 months.');
  }

  function renderDailyChart(daily) {
    var empty = daily.every(function (x) { return x.sales === 0; });
    drawChart('daily', 'dailyChart', {
      type: 'line',
      data: {
        labels: daily.map(function (x) { return x.day; }),
        datasets: [{
          label: 'Sales',
          data: daily.map(function (x) { return x.sales; }),
          borderColor: '#e60024', backgroundColor: 'rgba(230,0,36,.12)',
          fill: true, tension: 0.25, pointRadius: 2
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: {
          title: function (items) { return report.label.split(' ')[0] + ' ' + items[0].label; },
          label: function (c) { return ' ' + peso(c.parsed.y); }
        } } },
        scales: { y: { beginAtZero: true, ticks: { callback: moneyTick } }, x: { title: { display: true, text: 'Day of month' } } }
      }
    }, empty, 'No sales recorded this month.');
  }

  function renderProductCharts() {
    var ranked = getRanked();
    var by = $('rankPicker').value === 'sales' ? 'sales' : 'qty';
    var top = ranked.slice(0, 10);

    drawChart('best', 'bestChart', {
      type: 'bar',
      data: {
        labels: top.map(function (p) { return p.name; }),
        datasets: [{
          label: by === 'sales' ? 'Sales' : 'Quantity (kg)',
          data: top.map(function (p) { return p[by]; }),
          backgroundColor: '#e60024', borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) {
          return by === 'sales' ? ' ' + peso(c.parsed.x) : ' ' + kg(c.parsed.x) + ' kg';
        } } } },
        scales: { x: { beginAtZero: true, ticks: { callback: function (v) { return by === 'sales' ? moneyTick(v) : v; } } } }
      }
    }, !top.length, 'No products sold this month.');

    // Share of sales: top 5 by sales + "Others"
    var bySales = report.products.slice().sort(function (a, b) { return b.sales - a.sales; });
    var top5 = bySales.slice(0, 5);
    var others = bySales.slice(5).reduce(function (sum, p) { return sum + p.sales; }, 0);
    var labels = top5.map(function (p) { return p.name; });
    var values = top5.map(function (p) { return p.sales; });
    if (others > 0) { labels.push('Others'); values.push(Math.round(others * 100) / 100); }

    drawChart('share', 'shareChart', {
      type: 'doughnut',
      data: { labels: labels, datasets: [{ data: values, backgroundColor: PALETTE, borderWidth: 1 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right' },
          tooltip: { callbacks: { label: function (c) {
            var total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
            var pct = total ? (c.parsed / total * 100).toFixed(1) : '0.0';
            return ' ' + c.label + ': ' + peso(c.parsed) + ' (' + pct + '%)';
          } } }
        }
      }
    }, !values.length, 'No products sold this month.');
  }

  /* ---------- tables ---------- */
  function renderBestTable() {
    var ranked = getRanked().slice(0, 10);
    var body = $('bestBody');
    if (!ranked.length) {
      body.innerHTML = '<tr><td colspan="5" class="empty">No products sold in ' + esc(report.label) + '.</td></tr>';
      return;
    }
    body.innerHTML = ranked.map(function (p, i) {
      return '<tr><td>' + (i + 1) + '</td><td><strong>' + esc(p.name) + '</strong></td>' +
        '<td class="num">' + kg(p.qty) + '</td><td class="num">' + peso(p.sales) + '</td>' +
        '<td class="num">' + p.orders + '</td></tr>';
    }).join('');
  }

  function monthChange(trend, i) {
    if (i === 0) return null;
    var prev = trend[i - 1].sales;
    if (prev <= 0) return null;
    return ((trend[i].sales - prev) / prev) * 100;
  }

  function renderMonthlyTable(trend) {
    var body = $('monthlyBody');
    body.innerHTML = trend.map(function (t, i) {
      var c = monthChange(trend, i);
      var cell = '—';
      if (c !== null) {
        cell = '<span class="' + (c >= 0 ? 'chg-up' : 'chg-down') + '">' + (c >= 0 ? '▲ ' : '▼ ') + pctText(c) + '</span>';
      }
      return '<tr' + (i === trend.length - 1 ? ' class="current-row"' : '') + '><td>' + esc(t.label) + '</td>' +
        '<td class="num">' + t.orders + '</td><td class="num">' + kg(t.units) + '</td>' +
        '<td class="num">' + peso(t.sales) + '</td><td class="num">' + cell + '</td></tr>';
    }).reverse().join('');
  }

  /* ---------- CSV ---------- */
  function csvCell(v) {
    var s = String(v == null ? '' : v);
    // Guard against spreadsheet formula injection from product names.
    if (/^[=+\-@]/.test(s)) s = "'" + s;
    return '"' + s.replace(/"/g, '""') + '"';
  }

  function downloadCSV() {
    if (!report) return;
    var rows = [['Month', 'Orders', 'Quantity (kg)', 'Sales (PHP)']];
    report.trend.forEach(function (t) { rows.push([t.label, t.orders, t.units, t.sales.toFixed(2)]); });
    rows.push([]);
    rows.push(['Best sellers — ' + report.label]);
    rows.push(['Rank', 'Product', 'Quantity (kg)', 'Sales (PHP)', 'Orders']);
    getRanked().slice(0, 10).forEach(function (p, i) { rows.push([i + 1, p.name, p.qty, p.sales.toFixed(2), p.orders]); });

    var csv = rows.map(function (r) { return r.map(csvCell).join(','); }).join('\r\n');
    var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'rsa-sales-report-' + report.month + '.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  /* ---------- events ---------- */
  async function logout() {
    try {
      await fetch('../api/owner-logout.php', { method: 'POST', credentials: 'same-origin' });
    } catch (e) { /* ignore */ }
    window.location.href = 'index.php';
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('monthPicker').addEventListener('change', load);
    $('basisPicker').addEventListener('change', load);
    $('rankPicker').addEventListener('change', function () {
      if (!report) return;
      renderProductCharts();
      renderBestTable();
    });
    $('csvBtn').addEventListener('click', downloadCSV);
    $('logoutBtn').addEventListener('click', logout);
    $('printBtn').addEventListener('click', function () {
      $('printDate').textContent = new Date().toLocaleString('en-US', { dateStyle: 'full', timeStyle: 'short' });
      window.print();
    });
    load();
  });
})();
