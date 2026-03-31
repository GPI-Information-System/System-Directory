/* VIEWER MAINTENANCE — JavaScript */

const VM_REFRESH_INTERVAL = 60;
const VM_DONE_PAGE_SIZE = 3;

let vmDoneShowAll = false;
let vmRefreshSecondsLeft = VM_REFRESH_INTERVAL;
let vmRefreshTimer = null;

/*AUTO-REFRESH*/
function vmStartRefreshCycle() {
  vmRefreshSecondsLeft = VM_REFRESH_INTERVAL;
  vmUpdateRefreshRing(VM_REFRESH_INTERVAL);
  vmRefreshTimer = setInterval(function () {
    vmRefreshSecondsLeft--;
    vmUpdateRefreshRing(vmRefreshSecondsLeft);
    if (vmRefreshSecondsLeft <= 0) {
      clearInterval(vmRefreshTimer);
      const url = new URL(location.href);
      const search = document.getElementById("vmSearch")?.value || "";
      if (search) url.searchParams.set("q", search);
      url.searchParams.set("filter", vmActiveFilter);
      location.replace(url.toString());
    }
  }, 1000);
}

function vmUpdateRefreshRing(secondsLeft) {
  const ring = document.getElementById("vmRefreshRing");
  if (!ring) return;
  const icon = ring.querySelector(".vm-refresh-icon");
  if (icon) {
    const deg =
      ((VM_REFRESH_INTERVAL - secondsLeft) / VM_REFRESH_INTERVAL) * 360;
    icon.style.transform = `rotate(${deg}deg)`;
  }
  ring.title = `Auto-refreshes in ${secondsLeft}s`;
}

function vmInitStickyControls() {
  const sticky = document.getElementById("vmStickyControls");
  if (!sticky) return;
  const sentinel = document.createElement("div");
  sentinel.style.cssText = "height:1px;margin-bottom:-1px;pointer-events:none;";
  sticky.parentNode.insertBefore(sentinel, sticky);
  const observer = new IntersectionObserver(
    ([entry]) => {
      sticky.classList.toggle("is-stuck", !entry.isIntersecting);
    },
    { threshold: 0, rootMargin: "-1px 0px 0px 0px" },
  );
  observer.observe(sentinel);
}

function vmToggleAlertDropdown() {
  if (!VM_HAS_ACTIVE) return;
  const banner = document.querySelector(".vm-alert-banner");
  const toggle = document.getElementById("vmAlertToggle");
  const dropdown = document.getElementById("vmAlertDropdown");
  if (!banner || !dropdown) return;
  const isOpen = banner.classList.contains("vm-alert-open");
  banner.classList.toggle("vm-alert-open", !isOpen);
  toggle.setAttribute("aria-expanded", String(!isOpen));
  dropdown.setAttribute("aria-hidden", String(isOpen));
}

function vmRenderCountdowns() {
  document.querySelectorAll(".vm-countdown[data-end]").forEach(vmTickCountdown);
}

function vmTickCountdown(el) {
  const endTime = new Date(el.getAttribute("data-end")).getTime();
  const diffMs = endTime - Date.now();
  if (diffMs <= 0) {
    if (!el.hasAttribute("data-reloading")) {
      el.setAttribute("data-reloading", "1");
      const contact = el.getAttribute("data-contact") || "";
      const callPart = contact
        ? `, or call <strong>${contact}</strong> for assistance`
        : "";
      el.innerHTML = `<span class="vm-countdown-badge vm-countdown-overdue">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;align-self:flex-start;margin-top:1px"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>Past scheduled end — update expected shortly, thank you for your patience${callPart}</span>
            </span>`;
      setTimeout(function () {
        clearInterval(vmRefreshTimer);
        const url = new URL(location.href);
        url.searchParams.set("filter", vmActiveFilter);
        const q = document.getElementById("vmSearch")?.value || "";
        if (q) url.searchParams.set("q", q);
        location.replace(url.toString());
      }, 30000);
    }
    return;
  }
  const totalSecs = Math.floor(diffMs / 1000);
  const h = Math.floor(totalSecs / 3600);
  const m = Math.floor((totalSecs % 3600) / 60);
  const s = totalSecs % 60;
  const endsIn = vmIsJapanese ? "あと" : "Ends in";
  const label =
    h > 0
      ? `${endsIn} ${h}h ${m}m`
      : m > 0
        ? `${endsIn} ${m}m ${s}s`
        : `${endsIn} ${s}s`;
  const urgency =
    totalSecs < 300
      ? "vm-countdown-urgent"
      : totalSecs < 900
        ? "vm-countdown-soon"
        : "vm-countdown-normal";
  el.innerHTML = `<span class="vm-countdown-badge ${urgency}">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        ${label}
    </span>`;
}

function vmStartCountdowns() {
  vmRenderCountdowns();
  setInterval(vmRenderCountdowns, 1000);
}

function vmFormatRelativeTime(dateStr) {
  const now = Date.now();
  const then = new Date(dateStr).getTime();
  const diffSecs = Math.floor((now - then) / 1000);
  if (diffSecs < 60) return "just now";
  if (diffSecs < 3600) {
    const m = Math.floor(diffSecs / 60);
    return `${m}m ago`;
  }
  if (diffSecs < 86400) {
    const h = Math.floor(diffSecs / 3600);
    return `${h}h ago`;
  }
  const days = Math.floor(diffSecs / 86400);
  if (days === 1) return "yesterday";
  if (days < 7) return `${days} days ago`;
  if (days < 30) {
    const w = Math.floor(days / 7);
    return `${w} week${w > 1 ? "s" : ""} ago`;
  }
  if (days < 365) {
    const mo = Math.floor(days / 30);
    return `${mo} month${mo > 1 ? "s" : ""} ago`;
  }
  const yr = Math.floor(days / 365);
  return `${yr} year${yr > 1 ? "s" : ""} ago`;
}

function vmRenderRelativeTimes() {
  document.querySelectorAll(".vm-relative-time[data-updated]").forEach((el) => {
    el.textContent = vmFormatRelativeTime(el.getAttribute("data-updated"));
  });
}

function vmHighlightText(text, query) {
  if (!query) return escVm(text);
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const regex = new RegExp(`(${escaped})`, "gi");
  return escVm(text).replace(regex, '<mark class="vm-highlight">$1</mark>');
}

function vmApplyHighlights() {
  const query = vmActiveSearch;
  document.querySelectorAll(".vm-system-name").forEach((el) => {
    const original = el.getAttribute("title") || el.textContent;
    el.innerHTML = vmHighlightText(original, query);
  });
  document.querySelectorAll(".vm-maint-title[data-original]").forEach((el) => {
    el.innerHTML = vmHighlightText(el.getAttribute("data-original"), query);
  });
}

/* SEARCH & FILTER*/
function vmApplyFilters() {
  const cards = document.querySelectorAll(".vm-card");
  const filterEmpty = document.getElementById("vmFilterEmpty");
  const resultCount = document.getElementById("vmResultCount");
  let visible = 0;

  cards.forEach((card) => {
    const cardStatus = card.getAttribute("data-status") || "";
    const cardSearch = card.getAttribute("data-search") || "";
    const matchFilter =
      vmActiveFilter === "all" || cardStatus === vmActiveFilter;
    const matchSearch =
      vmActiveSearch === "" || cardSearch.includes(vmActiveSearch);
    if (matchFilter && matchSearch) {
      card.classList.remove("vm-hidden");
      visible++;
    } else card.classList.add("vm-hidden");
  });

  vmApplyDonePagination();
  vmApplyHighlights();

  if (resultCount) {
    if (vmIsJapanese) {
      const statusLabelsJP = {
        "In Progress": "進行中",
        Scheduled: "予定済み",
        Done: "完了",
      };
      let txt = `<strong>${visible}</strong>件`;
      if (vmActiveFilter !== "all")
        txt += `の${statusLabelsJP[vmActiveFilter] || vmActiveFilter}`;
      txt += "のメンテナンス記録を表示中";
      if (vmActiveSearch)
        txt += `（"<em>${escVm(vmActiveSearch)}</em>"に一致）`;
      if (visible < VM_TOTAL)
        txt += ` <span class="vm-count-total">（全${VM_TOTAL}件）</span>`;
      resultCount.innerHTML = txt;
    } else {
      const statusLabels = {
        "In Progress": "in-progress",
        Scheduled: "scheduled",
        Done: "completed",
      };
      let txt = `Showing <strong>${visible}</strong>`;
      if (vmActiveFilter !== "all")
        txt += ` ${statusLabels[vmActiveFilter] || vmActiveFilter}`;
      txt += ` maintenance record${visible !== 1 ? "s" : ""}`;
      if (vmActiveSearch)
        txt += ` matching "<em>${escVm(vmActiveSearch)}</em>"`;
      if (visible < VM_TOTAL)
        txt += ` <span class="vm-count-total">(${VM_TOTAL} total)</span>`;
      resultCount.innerHTML = txt;
    }
  }

  if (filterEmpty) {
    if (visible === 0) {
      filterEmpty.style.display = "block";
      const titleEl = document.getElementById("vmFilterEmptyTitle");
      const msgEl = document.getElementById("vmFilterEmptyMsg");
      const emptyTitles = vmIsJapanese
        ? {
            "In Progress": "アクティブなメンテナンスなし",
            Scheduled: "予定なし",
            Done: "完了記録なし",
            all: "記録が見つかりません",
          }
        : {
            "In Progress": "No Active Maintenance",
            Scheduled: "Nothing Scheduled",
            Done: "No Completed Records",
            all: "No Records Found",
          };
      const emptyMsgs = vmIsJapanese
        ? {
            "In Progress": "現在メンテナンス中のシステムはありません。",
            Scheduled: "現在予定されているメンテナンスはありません。",
            Done: "まだ完了したメンテナンスはありません。",
            all: vmActiveSearch
              ? `"${escVm(vmActiveSearch)}"に一致する記録はありません。`
              : "メンテナンス記録はありません。",
          }
        : {
            "In Progress": "There are no systems currently under maintenance.",
            Scheduled: "No maintenance windows are currently scheduled.",
            Done: "No maintenance has been completed yet.",
            all: vmActiveSearch
              ? `No records match "${escVm(vmActiveSearch)}".`
              : "No maintenance records available.",
          };
      if (titleEl)
        titleEl.textContent =
          emptyTitles[vmActiveFilter] ||
          (vmIsJapanese ? "結果が見つかりません" : "No Results Found");
      if (msgEl)
        msgEl.textContent =
          emptyMsgs[vmActiveFilter] ||
          (vmIsJapanese
            ? "条件に一致するメンテナンス記録はありません。"
            : "No records match your filter.");
    } else {
      filterEmpty.style.display = "none";
    }
  }

  const allClearBar = document.getElementById("vmAllClearBar");
  if (allClearBar) {
    allClearBar.style.display =
      vmActiveFilter === "all" || vmActiveFilter === "Done" ? "flex" : "none";
  }
}

function vmApplyDonePagination() {
  if (vmActiveFilter !== "all" && vmActiveFilter !== "Done") return;
  const doneCards = Array.from(
    document.querySelectorAll(".vm-card:not(.vm-hidden)"),
  ).filter((c) => c.getAttribute("data-status") === "Done");
  const showMoreBtn = document.getElementById("vmShowMoreDone");
  const showMoreWrap = document.getElementById("vmShowMoreWrap");
  if (doneCards.length <= VM_DONE_PAGE_SIZE) {
    doneCards.forEach((c) => c.classList.remove("vm-done-hidden"));
    if (showMoreWrap) showMoreWrap.style.display = "none";
    return;
  }
  if (vmDoneShowAll) {
    doneCards.forEach((c) => c.classList.remove("vm-done-hidden"));
    if (showMoreWrap) showMoreWrap.style.display = "none";
  } else {
    doneCards.forEach((c, i) => {
      c.classList.toggle("vm-done-hidden", i >= VM_DONE_PAGE_SIZE);
    });
    if (showMoreWrap) {
      const hidden = doneCards.length - VM_DONE_PAGE_SIZE;
      showMoreWrap.style.display = "flex";
      const showMoreTextEl = document.getElementById("vmShowMoreText");
      if (showMoreTextEl)
        showMoreTextEl.textContent = vmIsJapanese
          ? `過去の記録を${hidden}件表示`
          : `Show ${hidden} older record${hidden !== 1 ? "s" : ""}`;
    }
  }
}

function vmToggleDoneHistory() {
  vmDoneShowAll = true;
  vmApplyDonePagination();
}

function vmFilter() {
  const input = document.getElementById("vmSearch");
  vmActiveSearch = input ? input.value.trim().toLowerCase() : "";
  vmApplyFilters();
}

function vmSetFilter(status, btnEl) {
  vmActiveFilter = status;
  vmDoneShowAll = false;
  document
    .querySelectorAll(".vm-filter-btn")
    .forEach((b) =>
      b.classList.toggle("active", b.getAttribute("data-status") === status),
    );
  document
    .querySelectorAll(".vm-sheet-option")
    .forEach((b) =>
      b.classList.toggle("active", b.getAttribute("data-status") === status),
    );
  vmUpdateFilterSheetBadge(status);
  vmApplyFilters();
}

function vmOpenFilterSheet() {
  const sheet = document.getElementById("vmBottomSheet");
  const overlay = document.getElementById("vmSheetOverlay");
  if (!sheet || !overlay) return;
  overlay.style.display = "block";
  sheet.style.display = "block";
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      overlay.classList.add("is-open");
      sheet.classList.add("is-open");
    });
  });
  document.body.style.overflow = "hidden";
}

function vmCloseFilterSheet() {
  const sheet = document.getElementById("vmBottomSheet");
  const overlay = document.getElementById("vmSheetOverlay");
  if (!sheet || !overlay) return;
  overlay.classList.remove("is-open");
  sheet.classList.remove("is-open");
  document.body.style.overflow = "";
  setTimeout(() => {
    overlay.style.display = "none";
    sheet.style.display = "none";
  }, 300);
}

function vmSheetFilter(status, btnEl) {
  vmSetFilter(status, btnEl);
  vmCloseFilterSheet();
}

function vmUpdateFilterSheetBadge(status) {
  const btn = document.getElementById("vmFilterSheetBtn");
  const badge = document.getElementById("vmFilterSheetBadge");
  if (!btn || !badge) return;
  const isFiltered = status !== "all";
  btn.classList.toggle("has-filter", isFiltered);
  badge.style.display = isFiltered ? "block" : "none";
  if (isFiltered) badge.title = `Filtering: ${status}`;
}

function vmRestoreState() {
  const params = new URLSearchParams(location.search);
  const filter = params.get("filter");
  const q = params.get("q");
  if (filter && filter !== "all") {
    vmActiveFilter = filter;
    document
      .querySelectorAll(".vm-filter-btn")
      .forEach((b) =>
        b.classList.toggle("active", b.getAttribute("data-status") === filter),
      );
    document
      .querySelectorAll(".vm-sheet-option")
      .forEach((b) =>
        b.classList.toggle("active", b.getAttribute("data-status") === filter),
      );
    vmUpdateFilterSheetBadge(filter);
  }
  if (q) {
    const input = document.getElementById("vmSearch");
    if (input) {
      input.value = q;
      vmActiveSearch = q.toLowerCase();
    }
  }
  vmApplyFilters();
}

document.addEventListener("keydown", function (e) {
  const toggle = document.getElementById("vmAlertToggle");
  if (
    toggle &&
    document.activeElement === toggle &&
    (e.key === "Enter" || e.key === " ")
  ) {
    e.preventDefault();
    vmToggleAlertDropdown();
  }
  const input = document.getElementById("vmSearch");
  if (e.key === "Escape" && document.activeElement === input) {
    input.value = "";
    vmActiveSearch = "";
    vmApplyFilters();
    input.blur();
  }
  if (e.key === "/" && document.activeElement.tagName !== "INPUT") {
    e.preventDefault();
    if (input) input.focus();
  }
  if (e.key === "Escape") vmCloseFilterSheet();
});

function escVm(str) {
  const d = document.createElement("div");
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

const VM_JP = {
  pageTitle: "メンテナンススケジュール",
  pageSubtitle: "スケジュール済み、進行中、完了したメンテナンス",
  backBtn: "システムディレクトリ",
  alertTitle: "アクティブメンテナンス進行中",
  alertSubtitle: "システムは現在メンテナンス中です。",
  alertViewHere: "こちらを見る",
  alertBadge: (n) => `${n}システムに影響`,
  dropdownLabel: (n) => `${n}システムが現在メンテナンス中`,
  underMaint: "メンテナンス中",
  contact: (num) => `支援のために ${num} にお問い合わせください`,
  filterTitle: "ステータスでフィルター",
  filterAll: "全て",
  filterInProgress: "進行中",
  filterScheduled: "予定済み",
  filterDone: "完了",
  filterSheetLabel: "フィルター",
  sheetAll: "全記録",
  labelStart: "開始",
  labelEnd: "終了",
  inProgress: "メンテナンス進行中",
  starts: "開始",
  onTime: "予定通り完了",
  overSchedule: (t) => t.replace("over schedule", "スケジュール超過"),
  showMore: "過去の記録を表示",
  allClearBarFn: (n) => `全システム正常稼働中 — 完了記録${n}件`,
  allClearTitle: "全システム正常稼働中",
  allClearMsg:
    "現在メンテナンスの予定はありません。全システムは正常に動作しています。",
  noResults: "結果が見つかりません",
  noResultsMsg: "条件に一致するメンテナンス記録はありません。",
  searchPlaceholder: "システムまたはタイトルで検索...",
  pillInProgress: (n) => `${n} 進行中`,
  pillScheduled: (n) => `${n} 予定済み`,
  pillCompleted: (n) => `${n} 完了`,
  status: { "In Progress": "進行中", Scheduled: "予定済み", Done: "完了" },
};

let vmIsJapanese = false;

function vmIsJpMode() {
  return localStorage.getItem("gportal_jp_mode") === "true";
}

function vmSetLanguage(lang) {
  vmIsJapanese = lang === "jp";
  localStorage.setItem("gportal_jp_mode", vmIsJapanese ? "true" : "false");
  vmUpdateLangUI();
  if (vmIsJapanese) vmApplyJP();
  else vmRevertEN();
  vmApplyFilters();
}

function vmUpdateLangUI() {
  const engBtn = document.getElementById("jpLangEng");
  const jpBtn = document.getElementById("jpLangJp");
  if (engBtn) engBtn.classList.toggle("active", !vmIsJapanese);
  if (jpBtn) jpBtn.classList.toggle("active", vmIsJapanese);
}

function vmSet(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function vmApplyJP() {
  vmSet("btnBackText", VM_JP.backBtn);
  vmSet("vmPageTitle", VM_JP.pageTitle);
  vmSet("vmPageSubtitle", VM_JP.pageSubtitle);
  vmSet("vmAlertTitle", VM_JP.alertTitle);
  vmSet("vmAlertSubtitle", VM_JP.alertSubtitle);
  vmSet("vmAlertViewHere", VM_JP.alertViewHere);
  const badge = document.getElementById("vmAlertBadge");
  if (badge) badge.textContent = VM_JP.alertBadge(VM_COUNT_ACTIVE);
  const dropLabel = document.getElementById("vmDropdownLabel");
  if (dropLabel) dropLabel.textContent = VM_JP.dropdownLabel(VM_COUNT_ACTIVE);
  document
    .querySelectorAll(".vm-under-maint-text")
    .forEach((el) => (el.textContent = VM_JP.underMaint));
  document.querySelectorAll(".vm-alert-contact").forEach((el) => {
    const num = el.getAttribute("data-number") || "";
    const svg = el.querySelector("svg");
    el.innerHTML = "";
    if (svg) el.appendChild(svg);
    el.appendChild(document.createTextNode(" " + VM_JP.contact(num)));
  });
  document
    .querySelectorAll(".vm-pill-inprogress-text")
    .forEach((el) => (el.textContent = VM_JP.pillInProgress(VM_COUNT_ACTIVE)));
  document
    .querySelectorAll(".vm-pill-scheduled-text")
    .forEach(
      (el) => (el.textContent = VM_JP.pillScheduled(VM_COUNT_SCHEDULED)),
    );
  document
    .querySelectorAll(".vm-pill-done-text")
    .forEach((el) => (el.textContent = VM_JP.pillCompleted(VM_COUNT_DONE)));
  vmSet("vmBtnAll", VM_JP.filterAll);
  vmSet("vmLabelInProgress", VM_JP.filterInProgress);
  vmSet("vmLabelScheduled", VM_JP.filterScheduled);
  vmSet("vmLabelDone", VM_JP.filterDone);
  vmSet("vmFilterSheetLabel", VM_JP.filterSheetLabel);
  vmSet("vmSheetTitle", VM_JP.filterTitle);
  vmSet("vmSheetAll", VM_JP.sheetAll);
  vmSet("vmSheetInProgress", VM_JP.filterInProgress);
  vmSet("vmSheetScheduled", VM_JP.filterScheduled);
  vmSet("vmSheetDone", VM_JP.filterDone);
  const search = document.getElementById("vmSearch");
  if (search) {
    search.setAttribute("data-en-ph", search.placeholder);
    search.placeholder = VM_JP.searchPlaceholder;
  }
  const barText = document.getElementById("vmAllClearBarText");
  if (barText) {
    barText.setAttribute("data-en", barText.textContent);
    barText.textContent = VM_JP.allClearBarFn(VM_COUNT_DONE);
  }
  const acTitle = document.getElementById("vmAllClearTitle");
  if (acTitle) {
    acTitle.setAttribute("data-en", acTitle.textContent);
    acTitle.textContent = VM_JP.allClearTitle;
  }
  const acMsg = document.getElementById("vmAllClearMsg");
  if (acMsg) {
    acMsg.setAttribute("data-en", acMsg.textContent);
    acMsg.textContent = VM_JP.allClearMsg;
  }
  vmSet("vmShowMoreText", VM_JP.showMore);
  document.querySelectorAll(".vm-status-text").forEach((el) => {
    const en =
      el.closest("[data-status-en]")?.getAttribute("data-status-en") ||
      el.textContent.trim();
    if (!el.getAttribute("data-en")) el.setAttribute("data-en", en);
    el.textContent = VM_JP.status[en] || en;
  });
  document.querySelectorAll(".vm-label-start").forEach((el) => {
    el.setAttribute("data-en", "Start");
    el.textContent = VM_JP.labelStart;
  });
  document.querySelectorAll(".vm-label-end").forEach((el) => {
    el.setAttribute("data-en", "End");
    el.textContent = VM_JP.labelEnd;
  });
  document.querySelectorAll(".vm-inprogress-text").forEach((el) => {
    el.setAttribute("data-en", el.textContent);
    el.textContent = VM_JP.inProgress;
  });
  document.querySelectorAll(".vm-starts-text").forEach((el) => {
    el.setAttribute("data-en", el.textContent);
    el.textContent = VM_JP.starts;
  });
  document.querySelectorAll(".vm-ontime-text").forEach((el) => {
    el.setAttribute("data-en", el.textContent);
    el.textContent = VM_JP.onTime;
  });
  document.querySelectorAll(".vm-exceeded-text").forEach((el) => {
    const en = el.getAttribute("data-en") || el.textContent;
    el.setAttribute("data-en", en);
    el.textContent = VM_JP.overSchedule(en);
  });
}

function vmRevertEN() {
  vmSet("btnBackText", "System Directory");
  vmSet("vmPageTitle", "Maintenance Schedule");
  vmSet(
    "vmPageSubtitle",
    "Scheduled, ongoing, and completed maintenance windows",
  );
  vmSet("vmAlertTitle", "Active Maintenance In Progress");
  vmSet("vmAlertSubtitle", "Systems are currently under maintenance.");
  vmSet("vmAlertViewHere", "View here");
  const badge = document.getElementById("vmAlertBadge");
  if (badge)
    badge.textContent = `${VM_COUNT_ACTIVE} system${VM_COUNT_ACTIVE !== 1 ? "s" : ""} affected`;
  const dropLabel = document.getElementById("vmDropdownLabel");
  if (dropLabel)
    dropLabel.textContent = `${VM_COUNT_ACTIVE} system${VM_COUNT_ACTIVE !== 1 ? "s" : ""} currently under maintenance`;
  document
    .querySelectorAll(".vm-under-maint-text")
    .forEach((el) => (el.textContent = "Under Maintenance"));
  document.querySelectorAll(".vm-alert-contact").forEach((el) => {
    const num = el.getAttribute("data-number") || "";
    const svg = el.querySelector("svg");
    el.innerHTML = "";
    if (svg) el.appendChild(svg);
    el.appendChild(document.createTextNode(" Contact "));
    const strong = document.createElement("strong");
    strong.textContent = num;
    el.appendChild(strong);
    el.appendChild(document.createTextNode(" for assistance"));
  });
  document
    .querySelectorAll(".vm-pill-inprogress-text")
    .forEach((el) => (el.textContent = `${VM_COUNT_ACTIVE} In Progress`));
  document
    .querySelectorAll(".vm-pill-scheduled-text")
    .forEach((el) => (el.textContent = `${VM_COUNT_SCHEDULED} Scheduled`));
  document
    .querySelectorAll(".vm-pill-done-text")
    .forEach((el) => (el.textContent = `${VM_COUNT_DONE} Completed`));
  vmSet("vmBtnAll", "All");
  vmSet("vmLabelInProgress", "In Progress");
  vmSet("vmLabelScheduled", "Scheduled");
  vmSet("vmLabelDone", "Done");
  vmSet("vmFilterSheetLabel", "Filter");
  vmSet("vmSheetTitle", "Filter by Status");
  vmSet("vmSheetAll", "All Records");
  vmSet("vmSheetInProgress", "In Progress");
  vmSet("vmSheetScheduled", "Scheduled");
  vmSet("vmSheetDone", "Done");
  const search = document.getElementById("vmSearch");
  if (search) {
    const en = search.getAttribute("data-en-ph");
    if (en) search.placeholder = en;
  }
  const barText = document.getElementById("vmAllClearBarText");
  if (barText) {
    const en = barText.getAttribute("data-en");
    if (en) barText.textContent = en;
  }
  const acTitle = document.getElementById("vmAllClearTitle");
  if (acTitle) {
    const en = acTitle.getAttribute("data-en");
    if (en) acTitle.textContent = en;
  }
  const acMsg = document.getElementById("vmAllClearMsg");
  if (acMsg) {
    const en = acMsg.getAttribute("data-en");
    if (en) acMsg.textContent = en;
  }
  vmSet("vmShowMoreText", "Show older records");
  document.querySelectorAll(".vm-status-text").forEach((el) => {
    const en = el.getAttribute("data-en");
    if (en) el.textContent = en;
  });
  document
    .querySelectorAll(".vm-label-start")
    .forEach((el) => (el.textContent = "Start"));
  document
    .querySelectorAll(".vm-label-end")
    .forEach((el) => (el.textContent = "End"));
  document.querySelectorAll(".vm-inprogress-text").forEach((el) => {
    const en = el.getAttribute("data-en");
    if (en) el.textContent = en;
  });
  document
    .querySelectorAll(".vm-starts-text")
    .forEach((el) => (el.textContent = "Starts"));
  document
    .querySelectorAll(".vm-ontime-text")
    .forEach((el) => (el.textContent = "Completed on time"));
  document.querySelectorAll(".vm-exceeded-text").forEach((el) => {
    const en = el.getAttribute("data-en");
    if (en) el.textContent = en;
  });
}

function vmInitLanguage() {
  vmIsJapanese = vmIsJpMode();
  vmUpdateLangUI();
  if (vmIsJapanese) vmApplyJP();
}

document.addEventListener("DOMContentLoaded", function () {
  vmRestoreState();
  vmStartCountdowns();
  vmStartRefreshCycle();
  vmInitStickyControls();
  vmRenderRelativeTimes();
  setInterval(vmRenderRelativeTimes, 60000);
  vmInitLanguage();
});
