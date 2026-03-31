/* G-Portal Analytics Page JavaScript */

let uptimeChart = null;
let statusTrendsChart = null;
let currentUptimeView = "overall";
let currentHistoryDays = 30;

let allStatusLogs = [];
let filteredStatusLogs = [];
let currentPage = 1;
let itemsPerPage = 5;
let logDataMap = {};
let logSearchQuery = "";

let allCompletedMaint = [];
let filteredCompletedMaint = [];
let maintCurrentPage = 1;
let maintItemsPerPage = 5;
let currentMaintDays = 30;
let maintSearchQuery = "";

document.addEventListener("DOMContentLoaded", function () {
  loadStatusHistory(30);
  loadUptimeData();
  loadStatusTrends();
  loadCompletedMaintenance(30);
});

function switchUptimeView(view) {
  currentUptimeView = view;
  document.querySelectorAll(".toggle-btn[data-view]").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.view === view);
  });
  const combo = document.getElementById("uptimeSystemCombo");
  if (combo)
    combo.style.display = view === "per-system" ? "inline-flex" : "none";
  if (view !== "per-system") clearUptimeSystemSearch();
  loadUptimeData();
}

function showUptimeDropdown() {
  const dropdown = document.getElementById("uptimeSystemDropdown");
  if (dropdown) {
    dropdown.style.display = "block";
    filterUptimeSystems(document.getElementById("uptimeSystemSearch").value);
  }
}

function filterUptimeSystems(query) {
  const dropdown = document.getElementById("uptimeSystemDropdown");
  if (!dropdown) return;
  const options = dropdown.querySelectorAll(".report-system-option");
  const q = query.toLowerCase().trim();
  let visible = 0;
  options.forEach((opt) => {
    const matches = q === "" || opt.dataset.name.toLowerCase().includes(q);
    opt.style.display = matches ? "block" : "none";
    if (matches) visible++;
  });
  let noResults = dropdown.querySelector(".report-no-results");
  if (visible === 0) {
    if (!noResults) {
      noResults = document.createElement("div");
      noResults.className = "report-no-results";
      noResults.textContent = "No systems found";
      dropdown.appendChild(noResults);
    }
    noResults.style.display = "block";
  } else if (noResults) {
    noResults.style.display = "none";
  }
  const clearBtn = document.getElementById("uptimeSystemClearBtn");
  if (clearBtn) clearBtn.style.display = query ? "flex" : "none";
  dropdown.style.display = "block";
}

function selectUptimeSystem(id, name) {
  const select = document.getElementById("uptimeSystemSelect");
  if (select) select.value = id;
  const input = document.getElementById("uptimeSystemSearch");
  if (input) input.value = name;
  const clearBtn = document.getElementById("uptimeSystemClearBtn");
  if (clearBtn) clearBtn.style.display = "flex";
  const dropdown = document.getElementById("uptimeSystemDropdown");
  if (dropdown) dropdown.style.display = "none";
  document
    .querySelectorAll("#uptimeSystemDropdown .report-system-option")
    .forEach((opt) => {
      opt.classList.toggle("selected", parseInt(opt.dataset.id) === id);
    });
  loadUptimeData();
}

function clearUptimeSystemSearch() {
  const input = document.getElementById("uptimeSystemSearch");
  const select = document.getElementById("uptimeSystemSelect");
  const btn = document.getElementById("uptimeSystemClearBtn");
  if (input) input.value = "";
  if (select) select.value = "";
  if (btn) btn.style.display = "none";
  document
    .querySelectorAll("#uptimeSystemDropdown .report-system-option")
    .forEach((opt) => opt.classList.remove("selected"));
  const dropdown = document.getElementById("uptimeSystemDropdown");
  if (dropdown) dropdown.style.display = "none";
}

document.addEventListener("click", function (e) {
  const combo = document.getElementById("uptimeSystemCombo");
  if (combo && !combo.contains(e.target)) {
    const dropdown = document.getElementById("uptimeSystemDropdown");
    if (dropdown) dropdown.style.display = "none";
  }
});

function loadUptimeData() {
  const systemId =
    currentUptimeView === "per-system"
      ? document.getElementById("uptimeSystemSelect").value
      : 0;
  if (currentUptimeView === "per-system" && !systemId) {
    if (uptimeChart) {
      uptimeChart.destroy();
      uptimeChart = null;
    }
    updateUptimeStats(null);
    return;
  }
  fetch(
    "../backend/get_analytics_data.php?action=uptime_stats&system_id=" +
      systemId +
      "&days=30",
  )
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        renderUptimeChart(data.data, parseInt(systemId), data);
        calculateUptimeStats(data.data, parseInt(systemId), data);
      }
    })
    .catch((err) => console.error("Error loading uptime data:", err));
}

function renderUptimeChart(data, systemId, responseData) {
  const ctx = document.getElementById("uptimeChart").getContext("2d");
  const dates = [],
    uptimePercentages = [],
    tooltipDetails = [];

  if (systemId > 0) {
    const sorted = data
      .slice()
      .sort((a, b) => new Date(a.changed_at) - new Date(b.changed_at));
    const dailySeconds = {};
    const now = new Date();

    for (let i = 0; i < sorted.length; i++) {
      const segStart = new Date(sorted[i].changed_at);
      const segStatus = sorted[i].status;
      const segEnd =
        i + 1 < sorted.length ? new Date(sorted[i + 1].changed_at) : now;
      let cursor = new Date(segStart);
      while (cursor < segEnd) {
        const dayStr = cursor.toISOString().slice(0, 10);
        const dayEnd = new Date(cursor);
        dayEnd.setHours(23, 59, 59, 999);
        const sliceEnd = dayEnd < segEnd ? dayEnd : segEnd;
        const secs = (sliceEnd - cursor) / 1000;
        if (!dailySeconds[dayStr])
          dailySeconds[dayStr] = { online: 0, total: 0 };
        dailySeconds[dayStr].total += secs;
        if (segStatus === "online") dailySeconds[dayStr].online += secs;
        cursor = new Date(cursor);
        cursor.setDate(cursor.getDate() + 1);
        cursor.setHours(0, 0, 0, 0);
      }
    }

    Object.keys(dailySeconds)
      .sort()
      .forEach((date) => {
        const d = dailySeconds[date];
        const pct = d.total > 0 ? (d.online / d.total) * 100 : 0;
        dates.push(date);
        uptimePercentages.push(parseFloat(pct.toFixed(1)));
        tooltipDetails.push({
          pct: pct.toFixed(1),
          online: formatSecondsShort(d.online),
          total: formatSecondsShort(d.total),
        });
      });
  } else {
    const dailyData = {};
    data.forEach((entry) => {
      const date = entry.date;
      if (!dailyData[date]) dailyData[date] = { online: 0, total: 0 };
      const count = parseInt(entry.count);
      dailyData[date].total += count;
      if (entry.status === "online") dailyData[date].online += count;
    });

    Object.keys(dailyData)
      .sort()
      .forEach((date) => {
        const d = dailyData[date];
        const pct = d.total > 0 ? (d.online / d.total) * 100 : 0;
        dates.push(date);
        uptimePercentages.push(parseFloat(pct.toFixed(1)));
        tooltipDetails.push({
          pct: pct.toFixed(1),
          online: d.online + " online transitions",
          total: d.total + " total transitions",
        });
      });
  }

  if (uptimeChart) uptimeChart.destroy();

  const barColors = uptimePercentages.map((p) =>
    p >= 80
      ? "rgba(16,185,129,0.8)"
      : p >= 50
        ? "rgba(245,158,11,0.8)"
        : "rgba(239,68,68,0.8)",
  );
  const borderColors = uptimePercentages.map((p) =>
    p >= 80
      ? "rgba(16,185,129,1)"
      : p >= 50
        ? "rgba(245,158,11,1)"
        : "rgba(239,68,68,1)",
  );

  uptimeChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Uptime %",
          data: uptimePercentages,
          backgroundColor: barColors,
          borderColor: borderColors,
          borderWidth: 1,
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text:
            systemId > 0
              ? "Daily Uptime % (time-based)"
              : "Overall System Uptime — Last 30 Days",
          font: { size: 15, weight: "600" },
          color: "#1a1f36",
          padding: { bottom: 16 },
        },
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (ctx) => "Date: " + ctx[0].label,
            label: (ctx) => {
              const d = tooltipDetails[ctx.dataIndex];
              if (!d) return " " + ctx.parsed.y + "% uptime";
              return [
                " Uptime: " + d.pct + "%",
                " Online: " + d.online,
                " Tracked: " + d.total,
              ];
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          suggestedMax: Math.min(100, Math.max(...uptimePercentages) + 15),
          ticks: { callback: (v) => v + "%", stepSize: 20 },
          grid: { color: "rgba(0,0,0,0.05)" },
        },
        x: {
          grid: { display: false },
          ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 15 },
        },
      },
    },
  });
}

function calculateUptimeStats(data, systemId, responseData) {
  if (systemId > 0) {
    let incidents = 0;
    data.forEach((entry) => {
      if (entry.status === "down" || entry.status === "offline") incidents++;
    });
    const lastEntry = data.length > 0 ? data[data.length - 1] : null;
    const avgUptime =
      data.length > 0
        ? (
            (data.filter((e) => e.status === "online").length / data.length) *
            100
          ).toFixed(1)
        : 0;
    updateUptimeStats({
      current: lastEntry && lastEntry.status === "online" ? "100%" : "0%",
      average: avgUptime + "%",
      incidents,
    });
  } else {
    const currentPct = responseData.current_uptime || 0;
    const totalEntries = data.reduce((sum, e) => sum + parseInt(e.count), 0);
    const onlineEntries = data
      .filter((e) => e.status === "online")
      .reduce((sum, e) => sum + parseInt(e.count), 0);
    const avgUptime =
      totalEntries > 0 ? ((onlineEntries / totalEntries) * 100).toFixed(1) : 0;
    let incidents = 0;
    data.forEach((entry) => {
      if (entry.status === "down" || entry.status === "offline")
        incidents += parseInt(entry.count);
    });
    updateUptimeStats({
      current: currentPct + "%",
      average: avgUptime + "%",
      incidents,
    });
  }
}

function updateUptimeStats(stats) {
  if (!stats) {
    document.getElementById("currentUptime").textContent = "--";
    document.getElementById("avgUptime").textContent = "--";
    document.getElementById("totalIncidents").textContent = "--";
    return;
  }
  document.getElementById("currentUptime").textContent = stats.current;
  document.getElementById("avgUptime").textContent = stats.average;
  document.getElementById("totalIncidents").textContent = stats.incidents;
}

function formatSecondsShort(secs) {
  secs = Math.round(secs);
  if (secs < 60) return secs + "s";
  if (secs < 3600) return Math.floor(secs / 60) + "m";
  if (secs < 86400)
    return (
      Math.floor(secs / 3600) + "h " + Math.floor((secs % 3600) / 60) + "m"
    );
  return (
    Math.floor(secs / 86400) + "d " + Math.floor((secs % 86400) / 3600) + "h"
  );
}

function loadStatusHistory(days) {
  currentHistoryDays = days;
  currentPage = 1;
  document.querySelectorAll(".toggle-btn[data-days]").forEach((btn) => {
    btn.classList.toggle("active", parseInt(btn.dataset.days) === days);
  });
  const tbody = document.getElementById("statusHistoryBody");
  tbody.innerHTML =
    '<tr><td colspan="5" class="loading-cell">Loading...</td></tr>';
  logDataMap = {};
  fetch("../backend/get_analytics_data.php?action=status_history&days=" + days)
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        allStatusLogs = data.data;
        applyLogSearch();
      }
    })
    .catch(() => {
      tbody.innerHTML =
        '<tr><td colspan="5" class="loading-cell">Error loading data</td></tr>';
    });
}

function applyLogSearch() {
  const q = logSearchQuery.toLowerCase().trim();
  filteredStatusLogs = q
    ? allStatusLogs.filter(
        (l) =>
          (l.system_name || "").toLowerCase().includes(q) ||
          (l.old_status || "").toLowerCase().includes(q) ||
          (l.new_status || "").toLowerCase().includes(q) ||
          (l.change_note || "").toLowerCase().includes(q) ||
          (l.changed_by || "").toLowerCase().includes(q),
      )
    : [...allStatusLogs];
  currentPage = 1;
  renderStatusHistory();
}

function onLogSearch(input) {
  logSearchQuery = input.value;
  applyLogSearch();
}

function clearLogSearch() {
  logSearchQuery = "";
  const input = document.getElementById("logSearchInput");
  if (input) input.value = "";
  applyLogSearch();
}

function renderStatusHistory() {
  const tbody = document.getElementById("statusHistoryBody");
  if (filteredStatusLogs.length === 0) {
    const msg = logSearchQuery
      ? 'No results matching "<strong>' +
        escapeHtml(logSearchQuery) +
        '</strong>"'
      : "No status changes found";
    tbody.innerHTML =
      '<tr><td colspan="5" class="loading-cell">' + msg + "</td></tr>";
    updatePaginationInfo();
    return;
  }
  const totalPages = Math.ceil(filteredStatusLogs.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const logsToDisplay = filteredStatusLogs.slice(
    startIndex,
    startIndex + itemsPerPage,
  );
  logsToDisplay.forEach((log) => {
    logDataMap[log.id] = log;
  });
  tbody.innerHTML = logsToDisplay
    .map((log) => {
      const noteText = log.change_note ? escapeHtml(log.change_note) : "";
      return (
        "<tr>" +
        '<td class="col-date">' +
        formatDateTime(log.changed_at) +
        "</td>" +
        '<td class="col-system"><strong class="truncate-cell" title="' +
        escapeHtml(log.system_name) +
        '">' +
        escapeHtml(log.system_name) +
        "</strong></td>" +
        '<td class="col-status"><div class="status-change"><span class="status-badge ' +
        log.old_status +
        '">' +
        capitalize(log.old_status) +
        '</span><span class="arrow">→</span><span class="status-badge ' +
        log.new_status +
        '">' +
        capitalize(log.new_status) +
        "</span></div></td>" +
        '<td class="col-by"><span class="truncate-cell">' +
        escapeHtml(log.changed_by) +
        "</span></td>" +
        '<td class="col-note"><div class="note-cell">' +
        '<button class="btn-view-details" onclick="openViewDetailsModal(' +
        log.id +
        ')" title="View full details"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></button>' +
        '<span class="truncate-cell note-text" title="' +
        noteText +
        '">' +
        (noteText || "<em>No note</em>") +
        "</span>" +
        '<button class="btn-edit-note" onclick="openEditNoteModal_byId(' +
        log.id +
        ')" title="Edit note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Edit</button>' +
        "</div></td>" +
        "</tr>"
      );
    })
    .join("");
  updatePaginationInfo();
  renderPaginationControls(
    totalPages,
    currentPage,
    "paginationControls",
    changePage,
  );
}

function updatePaginationInfo() {
  const el = document.getElementById("paginationInfo");
  if (!el) return;
  const total = filteredStatusLogs.length;
  const s = total === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
  const e = Math.min(currentPage * itemsPerPage, total);
  const suffix =
    logSearchQuery && allStatusLogs.length !== total
      ? ' <span class="maint-filter-note">(' +
        allStatusLogs.length +
        " total)</span>"
      : "";
  el.innerHTML =
    "Showing " + s + "-" + e + " of " + total + " entries" + suffix;
}

function changePage(pageNumber) {
  const totalPages = Math.ceil(filteredStatusLogs.length / itemsPerPage);
  if (pageNumber < 1 || pageNumber > totalPages) return;
  currentPage = pageNumber;
  renderStatusHistory();
}

function loadCompletedMaintenance(days) {
  currentMaintDays = days;
  maintCurrentPage = 1;
  document.querySelectorAll(".toggle-btn[data-mdays]").forEach((btn) => {
    btn.classList.toggle("active", parseInt(btn.dataset.mdays) === days);
  });
  const tbody = document.getElementById("completedMaintenanceBody");
  tbody.innerHTML =
    '<tr><td colspan="7" class="loading-cell">Loading...</td></tr>';
  fetch(
    "../backend/get_analytics_data.php?action=completed_maintenance&days=" +
      days,
  )
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        allCompletedMaint = data.data;
        applyMaintSearch();
      } else {
        tbody.innerHTML =
          '<tr><td colspan="7" class="loading-cell">Error loading data</td></tr>';
      }
    })
    .catch(() => {
      tbody.innerHTML =
        '<tr><td colspan="7" class="loading-cell">Error loading data</td></tr>';
    });
}

function applyMaintSearch() {
  const q = maintSearchQuery.toLowerCase().trim();
  filteredCompletedMaint = q
    ? allCompletedMaint.filter(
        (m) =>
          (m.system_name || "").toLowerCase().includes(q) ||
          (m.title || "").toLowerCase().includes(q),
      )
    : [...allCompletedMaint];
  maintCurrentPage = 1;
  renderCompletedMaintenance();
}

function onMaintSearch(input) {
  maintSearchQuery = input.value;
  applyMaintSearch();
}

function clearMaintSearch() {
  maintSearchQuery = "";
  const input = document.getElementById("maintSearchInput");
  if (input) input.value = "";
  applyMaintSearch();
}

function renderCompletedMaintenance() {
  const tbody = document.getElementById("completedMaintenanceBody");
  if (filteredCompletedMaint.length === 0) {
    const msg = maintSearchQuery
      ? 'No results matching "<strong>' +
        escapeHtml(maintSearchQuery) +
        '</strong>"'
      : "No completed maintenance records found";
    tbody.innerHTML =
      '<tr><td colspan="7" class="loading-cell">' + msg + "</td></tr>";
    updateMaintPaginationInfo();
    return;
  }
  const totalPages = Math.ceil(
    filteredCompletedMaint.length / maintItemsPerPage,
  );
  const startIndex = (maintCurrentPage - 1) * maintItemsPerPage;
  const toDisplay = filteredCompletedMaint.slice(
    startIndex,
    startIndex + maintItemsPerPage,
  );
  tbody.innerHTML = toDisplay
    .map((m) => {
      const exceeded =
        m.exceeded_duration && parseInt(m.exceeded_duration) > 0
          ? formatExceeded(parseInt(m.exceeded_duration))
          : '<span class="no-exceeded">Within schedule</span>';
      const exceededClass =
        m.exceeded_duration && parseInt(m.exceeded_duration) > 0
          ? "exceeded-yes"
          : "exceeded-no";
      const actualEnd = m.actual_end_datetime
        ? '<span class="actual-end-time">' +
          formatDateTime(m.actual_end_datetime) +
          "</span>"
        : '<span class="actual-end-unknown">—</span>';
      let earlyBadge = "";
      if (m.actual_end_datetime && m.end_datetime) {
        const diff = new Date(m.end_datetime) - new Date(m.actual_end_datetime);
        if (diff > 60000) {
          const mins = Math.floor(diff / 60000);
          const hrs = Math.floor(mins / 60);
          const rem = mins % 60;
          earlyBadge =
            '<span class="early-finish-badge">' +
            (hrs > 0 ? hrs + "h " + rem + "m" : mins + "m") +
            " early</span>";
        }
      }
      return (
        "<tr>" +
        '<td class="col-system"><strong class="truncate-cell" title="' +
        escapeHtml(m.system_name) +
        '">' +
        escapeHtml(m.system_name) +
        "</strong></td>" +
        '<td class="col-title"><span class="truncate-cell" title="' +
        escapeHtml(m.title) +
        '">' +
        escapeHtml(m.title) +
        "</span></td>" +
        '<td class="col-date">' +
        formatDateTime(m.start_datetime) +
        "</td>" +
        '<td class="col-date">' +
        formatDateTime(m.end_datetime) +
        "</td>" +
        '<td class="col-actual-end">' +
        actualEnd +
        earlyBadge +
        "</td>" +
        '<td class="col-exceeded"><span class="' +
        exceededClass +
        '">' +
        exceeded +
        "</span></td>" +
        '<td class="col-by"><span class="truncate-cell" title="' +
        escapeHtml(m.done_by || "System") +
        '">' +
        escapeHtml(m.done_by || "System") +
        "</span></td>" +
        "</tr>"
      );
    })
    .join("");
  updateMaintPaginationInfo();
  renderPaginationControls(
    totalPages,
    maintCurrentPage,
    "maintPaginationControls",
    changeMaintPage,
  );
}

function formatExceeded(seconds) {
  if (!seconds || seconds <= 0)
    return '<span class="no-exceeded">Within schedule</span>';
  if (seconds < 60) return "+" + seconds + "s";
  if (seconds < 3600)
    return "+" + Math.floor(seconds / 60) + "m " + (seconds % 60) + "s";
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return "+" + h + "h " + m + "m";
}

function updateMaintPaginationInfo() {
  const el = document.getElementById("maintPaginationInfo");
  if (!el) return;
  const total = filteredCompletedMaint.length;
  const s = total === 0 ? 0 : (maintCurrentPage - 1) * maintItemsPerPage + 1;
  const e = Math.min(maintCurrentPage * maintItemsPerPage, total);
  const suffix =
    maintSearchQuery && allCompletedMaint.length !== total
      ? ' <span class="maint-filter-note">(' +
        allCompletedMaint.length +
        " total)</span>"
      : "";
  el.innerHTML =
    "Showing " + s + "-" + e + " of " + total + " entries" + suffix;
}

function changeMaintPage(pageNumber) {
  const totalPages = Math.ceil(
    filteredCompletedMaint.length / maintItemsPerPage,
  );
  if (pageNumber < 1 || pageNumber > totalPages) return;
  maintCurrentPage = pageNumber;
  renderCompletedMaintenance();
}

function renderPaginationControls(
  totalPages,
  activePage,
  containerId,
  changePageFn,
) {
  const container = document.getElementById(containerId);
  if (!container) return;
  if (totalPages <= 1) {
    container.innerHTML = "";
    return;
  }
  let html = "";
  html +=
    '<button class="pagination-btn pagination-prev" onclick="' +
    changePageFn.name +
    "(" +
    (activePage - 1) +
    ')" ' +
    (activePage === 1 ? "disabled" : "") +
    '><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Previous</button>';
  html += '<div class="pagination-numbers">';
  html +=
    '<button class="pagination-number ' +
    (activePage === 1 ? "active" : "") +
    '" onclick="' +
    changePageFn.name +
    '(1)">1</button>';
  if (activePage > 3) html += '<span class="pagination-dots">...</span>';
  for (
    let i = Math.max(2, activePage - 1);
    i <= Math.min(totalPages - 1, activePage + 1);
    i++
  ) {
    html +=
      '<button class="pagination-number ' +
      (activePage === i ? "active" : "") +
      '" onclick="' +
      changePageFn.name +
      "(" +
      i +
      ')">' +
      i +
      "</button>";
  }
  if (activePage < totalPages - 2)
    html += '<span class="pagination-dots">...</span>';
  if (totalPages > 1)
    html +=
      '<button class="pagination-number ' +
      (activePage === totalPages ? "active" : "") +
      '" onclick="' +
      changePageFn.name +
      "(" +
      totalPages +
      ')">' +
      totalPages +
      "</button>";
  html += "</div>";
  html +=
    '<button class="pagination-btn pagination-next" onclick="' +
    changePageFn.name +
    "(" +
    (activePage + 1) +
    ')" ' +
    (activePage === totalPages ? "disabled" : "") +
    '>Next <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></button>';
  container.innerHTML = html;
}

function loadMonthlyReport() {
  const systemId = document.getElementById("reportSystemSelect").value;
  const month = document.getElementById("reportMonthSelect").value;
  const container = document.getElementById("monthlyReportContainer");
  const exportBtn = document.getElementById("exportPdfBtn");
  if (!systemId) {
    container.innerHTML =
      '<div class="empty-state"><svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg><h4>Select a System and Month</h4><p>Choose a system and time period to generate a detailed monthly report</p></div>';
    exportBtn.disabled = true;
    return;
  }
  container.innerHTML =
    '<div class="loading-cell" style="padding:60px;">Loading report...</div>';
  exportBtn.disabled = true;
  fetch(
    "../backend/get_analytics_data.php?action=monthly_report&system_id=" +
      systemId +
      "&month=" +
      month,
  )
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        renderMonthlyReport(data.data);
        exportBtn.disabled = false;
      } else {
        container.innerHTML =
          '<div class="empty-state"><p>Error loading report</p></div>';
      }
    })
    .catch(() => {
      container.innerHTML =
        '<div class="empty-state"><p>Error loading report</p></div>';
    });
}

let reportLogData = [],
  reportLogPage = 1;
const reportLogPerPage = 5;

function changeReportLogPage(page) {
  const total = Math.ceil(reportLogData.length / reportLogPerPage);
  if (page < 1 || page > total) return;
  reportLogPage = page;
  renderReportLogTable();
}

function renderReportLogTable() {
  const tbody = document.getElementById("reportLogTbody");
  const paginInfo = document.getElementById("reportLogPaginInfo");
  const paginCtrl = document.getElementById("reportLogPaginCtrl");
  if (!tbody) return;
  const total = reportLogData.length,
    totalPages = Math.ceil(total / reportLogPerPage);
  const start = (reportLogPage - 1) * reportLogPerPage;
  const slice = reportLogData.slice(start, start + reportLogPerPage);
  tbody.innerHTML = slice
    .map(
      (change) =>
        "<tr>" +
        "<td>" +
        formatDateTime(change.changed_at) +
        "</td>" +
        '<td><span class="status-badge ' +
        change.old_status +
        '">' +
        capitalize(change.old_status) +
        '</span><span class="arrow">→</span><span class="status-badge ' +
        change.new_status +
        '">' +
        capitalize(change.new_status) +
        "</span></td>" +
        "<td>" +
        escapeHtml(change.changed_by) +
        "</td>" +
        "<td>" +
        (change.change_note ? escapeHtml(change.change_note) : "<em>—</em>") +
        "</td>" +
        "</tr>",
    )
    .join("");
  if (paginInfo) {
    const s = total === 0 ? 0 : start + 1;
    const e = Math.min(start + reportLogPerPage, total);
    paginInfo.textContent =
      "Showing " + s + "–" + e + " of " + total + " entries";
  }
  if (paginCtrl)
    renderPaginationControls(
      totalPages,
      reportLogPage,
      "reportLogPaginCtrl",
      changeReportLogPage,
    );
}

function renderMonthlyReport(reportData) {
  const container = document.getElementById("monthlyReportContainer");
  const monthName = new Date(reportData.month + "-01").toLocaleDateString(
    "en-US",
    { month: "long", year: "numeric" },
  );
  reportLogData = [...(reportData.status_changes || [])].reverse();
  reportLogPage = 1;
  window._dailyBreakdownData = reportData.daily_breakdown || [];
  window._dailyPage = 1;
  window._dailyPerPage = 5;

  let dailyHtml = "";
  if (reportData.daily_breakdown && reportData.daily_breakdown.length > 0) {
    dailyHtml =
      '<div class="report-section" id="dailyBreakdownSection"><h5>Daily Status Breakdown</h5><div class="daily-breakdown-table-wrapper"><table class="daily-breakdown-table"><thead><tr><th class="db-col-date">Date</th><th class="db-col-status">Status Activity</th></tr></thead><tbody id="dailyBreakdownTbody"></tbody></table></div><div class="pagination-container" style="border-radius:0 0 10px 10px;"><div class="pagination-info" id="dailyPaginInfo"></div><div class="pagination-controls" id="dailyPaginCtrl"></div></div></div>';
  }

  container.innerHTML =
    '<div class="report-content" id="reportContent">' +
    '<div class="report-header"><h4>' +
    escapeHtml(reportData.system.name) +
    ' — Monthly Report</h4><p class="report-period">' +
    monthName +
    "</p></div>" +
    '<div class="report-stats">' +
    '<div class="report-stat-card report-stat-card--uptime"><div class="label">Uptime Percentage</div><div class="value">' +
    reportData.uptime_percentage +
    "%</div></div>" +
    '<div class="report-stat-card report-stat-card--changes"><div class="label">Status Changes</div><div class="value">' +
    reportData.status_changes_count +
    "</div></div>" +
    '<div class="report-stat-card report-stat-card--downtime"><div class="label">Downtime Incidents</div><div class="value">' +
    reportData.downtime_incidents +
    "</div></div>" +
    "</div>" +
    '<div class="report-section"><h5>Total Time in Each Status</h5><div class="time-breakdown">' +
    Object.keys(reportData.time_in_status)
      .map((status) => {
        const info = reportData.time_in_status[status];
        const count = info.count || 0;
        const countBadge =
          count > 0
            ? '<span class="status-count-badge">' + count + "x</span>"
            : "";
        return (
          '<div class="time-breakdown-item"><div class="status-label"><span class="status-dot ' +
          status +
          '"></span>' +
          capitalize(status) +
          countBadge +
          '</div><div class="time-value">' +
          info.formatted +
          "</div></div>"
        );
      })
      .join("") +
    "</div></div>" +
    dailyHtml +
    (reportData.status_changes.length > 0
      ? '<div class="report-section"><h5>Status Change Log</h5><div class="table-container"><table class="data-table report-log-table"><thead><tr><th>Date & Time</th><th>Change</th><th>Changed By</th><th>Note</th></tr></thead><tbody id="reportLogTbody"></tbody></table><div class="pagination-container"><div class="pagination-info" id="reportLogPaginInfo"></div><div class="pagination-controls" id="reportLogPaginCtrl"></div></div></div></div>'
      : "") +
    "</div>";

  if (reportData.status_changes.length > 0) renderReportLogTable();
  if (window._dailyBreakdownData.length > 0) renderDailyBreakdown();
}

function loadStatusTrends() {
  fetch("../backend/get_analytics_data.php?action=status_history&days=30")
    .then((r) => r.json())
    .then((data) => {
      if (data.success) renderStatusTrendsChart(data.data);
    })
    .catch((err) => console.error("Error loading status trends:", err));
}

function renderStatusTrendsChart(logs) {
  const ctx = document.getElementById("statusTrendsChart").getContext("2d");
  const statusList = ["online", "offline", "maintenance", "down"];

  const dailyCounts = {};
  logs.forEach((log) => {
    const date = (log.changed_at || "").slice(0, 10);
    if (!date) return;
    if (!dailyCounts[date])
      dailyCounts[date] = { online: 0, offline: 0, maintenance: 0, down: 0 };
    if (dailyCounts[date][log.new_status] !== undefined)
      dailyCounts[date][log.new_status]++;
  });

  const dates = Object.keys(dailyCounts).sort();

  if (dates.length === 0) {
    if (statusTrendsChart) {
      statusTrendsChart.destroy();
      statusTrendsChart = null;
    }
    return;
  }

  const colors = {
    online: { bg: "rgba(16,185,129,0.75)", border: "rgba(16,185,129,1)" },
    offline: { bg: "rgba(107,114,128,0.75)", border: "rgba(107,114,128,1)" },
    maintenance: { bg: "rgba(245,158,11,0.75)", border: "rgba(245,158,11,1)" },
    down: { bg: "rgba(239,68,68,0.75)", border: "rgba(239,68,68,1)" },
  };

  const datasets = statusList.map((st) => ({
    label: capitalize(st),
    data: dates.map((d) => dailyCounts[d][st] || 0),
    backgroundColor: colors[st].bg,
    borderColor: colors[st].border,
    borderWidth: 1,
    borderRadius: 3,
    barPercentage: 0.5,
    categoryPercentage: 0.6,
    hidden: dates.every((d) => (dailyCounts[d][st] || 0) === 0),
  }));

  if (statusTrendsChart) statusTrendsChart.destroy();

  statusTrendsChart = new Chart(ctx, {
    type: "bar",
    data: { labels: dates, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: "Status Changes by Type — Last 30 Days",
          font: { size: 15, weight: "600" },
          color: "#1a1f36",
          padding: { bottom: 16 },
        },
        legend: {
          display: true,
          position: "bottom",
          labels: {
            usePointStyle: true,
            padding: 20,
            font: { size: 13 },
            filter: (item) => !item.hidden,
          },
        },
        tooltip: {
          mode: "index",
          intersect: false,
          callbacks: {
            title: (ctx) => "Date: " + ctx[0].label,
            label: (ctx) =>
              ctx.parsed.y === 0
                ? null
                : " " +
                  ctx.dataset.label +
                  ": " +
                  ctx.parsed.y +
                  " change" +
                  (ctx.parsed.y !== 1 ? "s" : ""),
          },
        },
      },
      scales: {
        x: {
          stacked: false,
          grid: { display: false },
          ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 15 },
        },
        y: {
          beginAtZero: true,
          stacked: false,
          suggestedMax: (function () {
            const maxVal = Math.max(
              ...statusList.map((st) =>
                Math.max(...dates.map((d) => dailyCounts[d][st] || 0)),
              ),
            );
            return maxVal + Math.max(1, Math.ceil(maxVal * 0.3));
          })(),
          ticks: {
            stepSize: 1,
            callback: (v) => (Number.isInteger(v) ? v : null),
          },
          grid: { color: "rgba(0,0,0,0.05)" },
        },
      },
    },
  });
}

function changeDailyPage(page) {
  const total = Math.ceil(
    window._dailyBreakdownData.length / window._dailyPerPage,
  );
  if (page < 1 || page > total) return;
  window._dailyPage = page;
  renderDailyBreakdown();
}

function renderDailyBreakdown() {
  const tbody = document.getElementById("dailyBreakdownTbody");
  const infoEl = document.getElementById("dailyPaginInfo");
  const ctrlEl = document.getElementById("dailyPaginCtrl");
  if (!tbody) return;
  const data = window._dailyBreakdownData;
  const total = data.length;
  const perPage = window._dailyPerPage;
  const page = window._dailyPage;
  const totalPages = Math.ceil(total / perPage);
  const start = (page - 1) * perPage;
  const slice = data.slice(start, start + perPage);
  const statusOrder = ["online", "maintenance", "down", "offline", "archived"];
  const statusColors = {
    online: "#10b981",
    maintenance: "#f59e0b",
    down: "#ef4444",
    offline: "#6b7280",
    archived: "#9ca3af",
  };
  tbody.innerHTML = slice
    .map((day) => {
      const pills = statusOrder
        .filter((st) => day.statuses[st])
        .map(
          (st) =>
            '<span class="db-pill db-pill--' +
            st +
            '"><span class="db-pill-dot" style="background:' +
            statusColors[st] +
            '"></span>' +
            capitalize(st) +
            ": " +
            day.statuses[st].formatted +
            "</span>",
        )
        .join("");
      return (
        '<tr><td class="db-col-date"><span class="db-date-label">' +
        day.label +
        '</span></td><td class="db-col-status"><div class="db-pills">' +
        pills +
        "</div></td></tr>"
      );
    })
    .join("");
  if (infoEl) {
    const s = total === 0 ? 0 : start + 1;
    const e = Math.min(start + perPage, total);
    infoEl.textContent = "Showing " + s + "-" + e + " of " + total + " days";
  }
  if (ctrlEl)
    renderPaginationControls(
      totalPages,
      page,
      "dailyPaginCtrl",
      changeDailyPage,
    );
}

function exportReportToPDF() {
  const reportContent = document.getElementById("reportContent");
  if (!reportContent) {
    alert("No report to export.");
    return;
  }
  const exportBtn = document.getElementById("exportPdfBtn");
  const origHTML = exportBtn.innerHTML;
  exportBtn.innerHTML =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg> Generating...';
  exportBtn.disabled = true;
  try {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF("p", "mm", "a4");
    const systemName =
      document.getElementById("reportSystemSelect").selectedOptions[0].text;
    const month = document.getElementById("reportMonthSelect").value;
    const monthName = new Date(month + "-01").toLocaleDateString("en-US", {
      month: "long",
      year: "numeric",
    });
    const marginL = 20,
      marginR = 20,
      pageW = 210,
      pageH = 297,
      contentW = pageW - marginL - marginR;
    let y = 20;
    pdf.setFillColor(22, 38, 96);
    pdf.rect(0, 0, pageW, 36, "F");
    pdf.setTextColor(255, 255, 255);
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(16);
    pdf.text("G-Portal", marginL, 14);
    pdf.setFontSize(11);
    pdf.setFont("helvetica", "normal");
    pdf.text("Analytics & Reports", marginL, 22);
    pdf.setFontSize(9);
    pdf.text("Generated: " + new Date().toLocaleString(), marginL, 30);
    y = 46;
    pdf.setTextColor(15, 23, 42);
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(16);
    const titleLines = pdf.splitTextToSize(systemName, contentW);
    titleLines.forEach((line) => {
      pdf.text(line, marginL, y);
      y += 8;
    });
    pdf.setFont("helvetica", "normal");
    pdf.setFontSize(12);
    pdf.setTextColor(107, 114, 128);
    pdf.text("Monthly Report — " + monthName, marginL, y);
    y += 12;
    pdf.setDrawColor(229, 231, 235);
    pdf.setLineWidth(0.5);
    pdf.line(marginL, y, pageW - marginR, y);
    y += 10;
    const uptimeVal = document.querySelector(
      ".report-stat-card--uptime  .value",
    )
      ? document.querySelector(".report-stat-card--uptime  .value").textContent
      : "--";
    const changesVal = document.querySelector(
      ".report-stat-card--changes  .value",
    )
      ? document.querySelector(".report-stat-card--changes  .value").textContent
      : "--";
    const downtimeVal = document.querySelector(
      ".report-stat-card--downtime .value",
    )
      ? document.querySelector(".report-stat-card--downtime .value").textContent
      : "--";
    const cardW = (contentW - 8) / 3;
    const cardData = [
      { label: "Uptime", value: uptimeVal, color: [16, 185, 129] },
      { label: "Status Changes", value: changesVal, color: [59, 130, 246] },
      { label: "Downtime Incidents", value: downtimeVal, color: [239, 68, 68] },
    ];
    cardData.forEach((card, i) => {
      const x = marginL + i * (cardW + 4);
      pdf.setFillColor(249, 250, 251);
      pdf.setDrawColor(229, 231, 235);
      pdf.setLineWidth(0.3);
      pdf.roundedRect(x, y, cardW, 22, 3, 3, "FD");
      pdf.setFillColor(...card.color);
      pdf.roundedRect(x, y, 3, 22, 1, 1, "F");
      pdf.setTextColor(107, 114, 128);
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(8);
      pdf.text(card.label, x + 7, y + 7);
      pdf.setTextColor(15, 23, 42);
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(18);
      pdf.text(card.value, x + 7, y + 17);
    });
    y += 30;
    pdf.setTextColor(15, 23, 42);
    pdf.setFont("helvetica", "bold");
    pdf.setFontSize(12);
    pdf.text("Total Time in Each Status", marginL, y);
    y += 8;
    const statusItems = document.querySelectorAll(".time-breakdown-item");
    const statusColors = {
      online: [16, 185, 129],
      offline: [107, 114, 128],
      maintenance: [245, 158, 11],
      down: [239, 68, 68],
      archived: [156, 163, 175],
    };
    statusItems.forEach((item) => {
      const label = item.querySelector(".status-label")
        ? item
            .querySelector(".status-label")
            .textContent.trim()
            .replace(/\s+/g, " ")
        : "";
      const value = item.querySelector(".time-value")
        ? item.querySelector(".time-value").textContent.trim()
        : "";
      pdf.setFillColor(249, 250, 251);
      pdf.setDrawColor(229, 231, 235);
      pdf.setLineWidth(0.3);
      pdf.roundedRect(marginL, y, contentW, 10, 2, 2, "FD");
      const dotColor = statusColors[label.toLowerCase().split(" ")[0]] || [
        156, 163, 175,
      ];
      pdf.setFillColor(...dotColor);
      pdf.circle(marginL + 7, y + 5, 2, "F");
      pdf.setTextColor(30, 41, 59);
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(10);
      pdf.text(label, marginL + 13, y + 6.5);
      pdf.setFont("helvetica", "bold");
      pdf.text(value, pageW - marginR - 2, y + 6.5, { align: "right" });
      y += 12;
    });
    y += 4;
    const allDailyData = window._dailyBreakdownData || [];
    if (allDailyData.length > 0) {
      if (y > pageH - 60) {
        pdf.addPage();
        y = 20;
      }
      pdf.setDrawColor(229, 231, 235);
      pdf.line(marginL, y, pageW - marginR, y);
      y += 8;
      pdf.setTextColor(15, 23, 42);
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      pdf.text("Daily Status Breakdown", marginL, y);
      y += 8;
      const dbCols = [
          { x: 0, w: 34 },
          { x: 34, w: contentW - 34 },
        ],
        dbHeaderH = 9;
      pdf.setFillColor(230, 235, 245);
      pdf.setDrawColor(0, 0, 0);
      pdf.setLineWidth(0.4);
      dbCols.forEach((col) =>
        pdf.rect(marginL + col.x, y, col.w, dbHeaderH, "FD"),
      );
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(8);
      pdf.setTextColor(30, 41, 59);
      pdf.text("DATE", marginL + dbCols[0].x + 2, y + 6);
      pdf.text("STATUS ACTIVITY", marginL + dbCols[1].x + 2, y + 6);
      y += dbHeaderH;
      const statusOrder = [
        "online",
        "maintenance",
        "down",
        "offline",
        "archived",
      ];
      allDailyData.forEach((day, idx) => {
        const rowH = 9,
          fillColor = idx % 2 === 0 ? [255, 255, 255] : [248, 250, 252];
        pdf.setDrawColor(0, 0, 0);
        pdf.setLineWidth(0.4);
        dbCols.forEach((col) => {
          pdf.setFillColor(...fillColor);
          pdf.rect(marginL + col.x, y, col.w, rowH, "FD");
        });
        pdf.setFont("helvetica", "bold");
        pdf.setFontSize(9);
        pdf.setTextColor(30, 41, 59);
        pdf.text(day.label, marginL + dbCols[0].x + 2, y + 6);
        let pillX = marginL + dbCols[1].x + 3;
        statusOrder
          .filter((st) => day.statuses[st])
          .forEach((st) => {
            const text = capitalize(st) + ": " + day.statuses[st].formatted;
            const dotColor = statusColors[st] || [156, 163, 175];
            pdf.setFillColor(...dotColor);
            pdf.circle(pillX + 1.5, y + 4.5, 1.5, "F");
            pdf.setFont("helvetica", "normal");
            pdf.setFontSize(8.5);
            pdf.setTextColor(51, 65, 85);
            pdf.text(text, pillX + 5, y + 6);
            pillX += pdf.getTextWidth(text) + 11;
          });
        y += rowH;
        if (y > pageH - 20) {
          pdf.addPage();
          y = 20;
        }
      });
    }
    if (reportLogData.length > 0) {
      if (y > pageH - 60) {
        pdf.addPage();
        y = 20;
      }
      pdf.setDrawColor(229, 231, 235);
      pdf.line(marginL, y, pageW - marginR, y);
      y += 8;
      pdf.setTextColor(15, 23, 42);
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      pdf.text("Status Change Log", marginL, y);
      y += 8;
      const cols = [
          { x: 0, w: 46 },
          { x: 46, w: 40 },
          { x: 86, w: 30 },
          { x: 116, w: contentW - 116 },
        ],
        headerH = 9;
      pdf.setFillColor(230, 235, 245);
      pdf.setDrawColor(0, 0, 0);
      pdf.setLineWidth(0.4);
      cols.forEach((col) => pdf.rect(marginL + col.x, y, col.w, headerH, "FD"));
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(8);
      pdf.setTextColor(30, 41, 59);
      pdf.text("DATE & TIME", marginL + cols[0].x + 2, y + 6);
      pdf.text("CHANGE", marginL + cols[1].x + 2, y + 6);
      pdf.text("CHANGED BY", marginL + cols[2].x + 2, y + 6);
      pdf.text("NOTE", marginL + cols[3].x + 2, y + 6);
      y += headerH;
      reportLogData.forEach((change, idx) => {
        const dateText = formatDateTimeForPDF(change.changed_at);
        const changeText =
          capitalize(change.old_status) +
          " -> " +
          capitalize(change.new_status);
        const byText = (change.changed_by || "").substring(0, 16);
        const noteText = change.change_note || "-";
        const noteMaxW = cols[3].w - 4;
        const noteLines = pdf.splitTextToSize(noteText, noteMaxW);
        const rowH = Math.max(9, noteLines.length * 5 + 4);
        const fillColor = idx % 2 === 0 ? [255, 255, 255] : [248, 250, 252];
        pdf.setLineWidth(0.4);
        pdf.setDrawColor(0, 0, 0);
        cols.forEach((col) => {
          pdf.setFillColor(...fillColor);
          pdf.rect(marginL + col.x, y, col.w, rowH, "FD");
        });
        pdf.setFont("helvetica", "normal");
        pdf.setFontSize(8.5);
        pdf.setTextColor(51, 65, 85);
        const textY = y + 6;
        pdf.text(dateText.substring(0, 22), marginL + cols[0].x + 2, textY);
        pdf.text(changeText.substring(0, 22), marginL + cols[1].x + 2, textY);
        pdf.text(byText, marginL + cols[2].x + 2, textY);
        pdf.text(noteLines, marginL + cols[3].x + 2, textY);
        y += rowH;
        if (y > pageH - 20) {
          pdf.addPage();
          y = 20;
        }
      });
    }
    const totalPages = pdf.internal.getNumberOfPages();
    for (let p = 1; p <= totalPages; p++) {
      pdf.setPage(p);
      pdf.setFillColor(249, 250, 251);
      pdf.rect(0, pageH - 12, pageW, 12, "F");
      pdf.setDrawColor(229, 231, 235);
      pdf.line(0, pageH - 12, pageW, pageH - 12);
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(8);
      pdf.setTextColor(107, 114, 128);
      pdf.text("G-Portal Analytics Report", marginL, pageH - 5);
      pdf.text("Page " + p + " of " + totalPages, pageW - marginR, pageH - 5, {
        align: "right",
      });
    }
    pdf.save(systemName + "-Report-" + month + ".pdf");
  } catch (err) {
    console.error("PDF error:", err);
    alert("Error generating PDF: " + err.message);
  }
  exportBtn.innerHTML = origHTML;
  exportBtn.disabled = false;
}

function openViewDetailsModal(id) {
  const log = logDataMap[id];
  if (!log) return;
  document.getElementById("vdSystemName").textContent = log.system_name;
  document.getElementById("vdSystem").textContent = log.system_name;
  document.getElementById("vdDateTime").textContent = formatDateTime(
    log.changed_at,
  );
  document.getElementById("vdChangedBy").textContent = log.changed_by;
  document.getElementById("vdStatusChange").innerHTML =
    '<span class="status-badge ' +
    log.old_status +
    '">' +
    capitalize(log.old_status) +
    '</span><span style="margin:0 6px;color:#9ca3af;font-weight:600;">→</span><span class="status-badge ' +
    log.new_status +
    '">' +
    capitalize(log.new_status) +
    "</span>";
  const noteEl = document.getElementById("vdNote");
  const note = log.change_note || "";
  if (note.trim()) {
    noteEl.textContent = note;
    noteEl.style.whiteSpace = "pre-wrap";
    noteEl.classList.remove("vd-note-empty");
  } else {
    noteEl.innerHTML = '<em style="color:#9ca3af;">No note provided</em>';
    noteEl.classList.add("vd-note-empty");
  }
  document.getElementById("viewDetailsModal").classList.add("show");
}

function closeViewDetailsModal() {
  document.getElementById("viewDetailsModal").classList.remove("show");
}

let currentEditLogId = null;

function openEditNoteModal_byId(id) {
  const log = logDataMap[id];
  if (!log) return;
  openEditNoteModal(log.id, log.change_note || "", log.system_name);
}

function openEditNoteModal(logId, currentNote, systemName) {
  currentEditLogId = logId;
  document.getElementById("editNoteSystemName").textContent = systemName;
  document.getElementById("editNoteTextarea").value =
    currentNote === "No note" ? "" : currentNote;
  document.getElementById("editNoteModal").classList.add("show");
  document.getElementById("editNoteTextarea").focus();
}

function closeEditNoteModal() {
  document.getElementById("editNoteModal").classList.remove("show");
  document.getElementById("editNoteTextarea").value = "";
  currentEditLogId = null;
}

function saveEditedNote(event) {
  event.preventDefault();
  if (!currentEditLogId) return;
  const newNote = document.getElementById("editNoteTextarea").value.trim();
  const formData = new FormData();
  formData.append("log_id", currentEditLogId);
  formData.append("note", newNote);
  const saveBtn = event.target.querySelector('button[type="submit"]');
  const origText = saveBtn.innerHTML;
  saveBtn.innerHTML = "Saving...";
  saveBtn.disabled = true;
  fetch("../backend/update_log_note.php", { method: "POST", body: formData })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        loadStatusHistory(currentHistoryDays);
        closeEditNoteModal();
        showSuccessMessage("Note updated successfully!");
      } else {
        alert(data.message || "Error updating note");
      }
    })
    .catch(() => {
      alert("Error updating note");
    })
    .finally(() => {
      saveBtn.innerHTML = origText;
      saveBtn.disabled = false;
    });
}

function showSuccessMessage(message) {
  const msgDiv = document.createElement("div");
  msgDiv.className = "success-toast";
  msgDiv.textContent = message;
  msgDiv.style.cssText =
    "position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:16px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;animation:slideIn 0.3s ease;";
  document.body.appendChild(msgDiv);
  setTimeout(() => {
    msgDiv.style.animation = "slideOut 0.3s ease";
    setTimeout(() => msgDiv.remove(), 300);
  }, 3000);
}

window.addEventListener("click", function (event) {
  if (event.target === document.getElementById("editNoteModal"))
    closeEditNoteModal();
  if (event.target === document.getElementById("viewDetailsModal"))
    closeViewDetailsModal();
});

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeEditNoteModal();
    closeViewDetailsModal();
  }
});

const style = document.createElement("style");
style.textContent =
  "@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}";
document.head.appendChild(style);

function formatDateTime(datetime) {
  return new Date(datetime).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatDateTimeForPDF(datetime) {
  const d = new Date(datetime);
  const months = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];
  const h = d.getHours(),
    m = String(d.getMinutes()).padStart(2, "0");
  const ampm = h >= 12 ? "PM" : "AM",
    h12 = h % 12 || 12;
  return (
    months[d.getMonth()] +
    " " +
    d.getDate() +
    ", " +
    d.getFullYear() +
    ", " +
    h12 +
    ":" +
    m +
    " " +
    ampm
  );
}

function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : "";
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function loadSystemsByStatus() {
  fetch("../backend/get_analytics_data.php?action=systems_by_status")
    .then((r) => r.json())
    .then((data) => {
      if (data.success && window.renderDashboardChart)
        window.renderDashboardChart(data.data);
    })
    .catch((err) => console.error("Error:", err));
}

function showReportDropdown() {
  const dropdown = document.getElementById("reportSystemDropdown");
  if (dropdown) {
    dropdown.style.display = "block";
    filterReportSystems(document.getElementById("reportSystemSearch").value);
  }
}

function hideReportDropdown() {
  setTimeout(() => {
    const dropdown = document.getElementById("reportSystemDropdown");
    if (dropdown) dropdown.style.display = "none";
  }, 180);
}

function filterReportSystems(query) {
  const dropdown = document.getElementById("reportSystemDropdown");
  if (!dropdown) return;
  const options = dropdown.querySelectorAll(".report-system-option");
  const q = query.toLowerCase().trim();
  let visible = 0;
  options.forEach((opt) => {
    const matches = q === "" || opt.dataset.name.toLowerCase().includes(q);
    opt.style.display = matches ? "block" : "none";
    if (matches) visible++;
  });
  let noResults = dropdown.querySelector(".report-no-results");
  if (visible === 0) {
    if (!noResults) {
      noResults = document.createElement("div");
      noResults.className = "report-no-results";
      noResults.textContent = "No systems found";
      dropdown.appendChild(noResults);
    }
    noResults.style.display = "block";
  } else if (noResults) {
    noResults.style.display = "none";
  }
  const clearBtn = document.getElementById("reportSystemClearBtn");
  if (clearBtn) clearBtn.style.display = query ? "flex" : "none";
  dropdown.style.display = "block";
}

function selectReportSystem(id, name) {
  const select = document.getElementById("reportSystemSelect");
  if (select) select.value = id;
  const input = document.getElementById("reportSystemSearch");
  if (input) input.value = name;
  const clearBtn = document.getElementById("reportSystemClearBtn");
  if (clearBtn) clearBtn.style.display = "flex";
  const dropdown = document.getElementById("reportSystemDropdown");
  if (dropdown) dropdown.style.display = "none";
  document.querySelectorAll(".report-system-option").forEach((opt) => {
    opt.classList.toggle("selected", parseInt(opt.dataset.id) === id);
  });
  loadMonthlyReport();
}

function clearReportSystemSearch() {
  const input = document.getElementById("reportSystemSearch");
  const select = document.getElementById("reportSystemSelect");
  const btn = document.getElementById("reportSystemClearBtn");
  if (input) input.value = "";
  if (select) select.value = "";
  if (btn) btn.style.display = "none";
  document
    .querySelectorAll(".report-system-option")
    .forEach((opt) => opt.classList.remove("selected"));
  filterReportSystems("");
  loadMonthlyReport();
}

document.addEventListener("click", function (e) {
  const combo = document.getElementById("reportSystemCombo");
  if (combo && !combo.contains(e.target)) {
    const dropdown = document.getElementById("reportSystemDropdown");
    if (dropdown) dropdown.style.display = "none";
  }
});
