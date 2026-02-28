/* ============================================================
   VIEWER MAINTENANCE — JavaScript
   Features:
     1. System name tooltip (native title attr + CSS)
     2. Card entrance animation (CSS-driven, staggered)
     3. Relative time on Done cards ("Completed 2 days ago")
     4. Search highlight (matched text highlighted yellow)
     5. Sticky filter bar (IntersectionObserver)
     6. Mobile bottom sheet for filters
   ============================================================ */

const VM_REFRESH_INTERVAL = 60;
const VM_DONE_PAGE_SIZE   = 3;

let vmDoneShowAll        = false;
let vmRefreshSecondsLeft = VM_REFRESH_INTERVAL;
let vmRefreshTimer       = null;

/* ─────────────────────────────────────────────
   AUTO-REFRESH
   ───────────────────────────────────────────── */

function vmStartRefreshCycle() {
    vmRefreshSecondsLeft = VM_REFRESH_INTERVAL;
    vmUpdateRefreshRing(VM_REFRESH_INTERVAL);

    vmRefreshTimer = setInterval(function () {
        vmRefreshSecondsLeft--;
        vmUpdateRefreshRing(vmRefreshSecondsLeft);
        if (vmRefreshSecondsLeft <= 0) {
            clearInterval(vmRefreshTimer);
            const search = document.getElementById('vmSearch')?.value || '';
            const url    = new URL(location.href);
            if (search) url.searchParams.set('q', search);
            url.searchParams.set('filter', vmActiveFilter);
            location.replace(url.toString());
        }
    }, 1000);
}

function vmUpdateRefreshRing(secondsLeft) {
    const ring = document.getElementById('vmRefreshRing');
    if (!ring) return;
    const icon = ring.querySelector('.vm-refresh-icon');
    if (icon) {
        const deg = ((VM_REFRESH_INTERVAL - secondsLeft) / VM_REFRESH_INTERVAL) * 360;
        icon.style.transform = `rotate(${deg}deg)`;
    }
    ring.title = `Auto-refreshes in ${secondsLeft}s`;
}

/* ─────────────────────────────────────────────
   FEATURE 5 — STICKY FILTER BAR
   Uses IntersectionObserver to detect when the
   controls bar has scrolled past the top
   ───────────────────────────────────────────── */

function vmInitStickyControls() {
    const sticky = document.getElementById('vmStickyControls');
    if (!sticky) return;

    // Sentinel: an invisible element placed just above the sticky bar
    const sentinel = document.createElement('div');
    sentinel.style.cssText = 'height:1px;margin-bottom:-1px;pointer-events:none;';
    sticky.parentNode.insertBefore(sentinel, sticky);

    const observer = new IntersectionObserver(
        ([entry]) => {
            sticky.classList.toggle('is-stuck', !entry.isIntersecting);
        },
        { threshold: 0, rootMargin: '-1px 0px 0px 0px' }
    );
    observer.observe(sentinel);
}

/* ─────────────────────────────────────────────
   ALERT BANNER DROPDOWN
   ───────────────────────────────────────────── */

function vmToggleAlertDropdown() {
    if (!VM_HAS_ACTIVE) return;
    const banner   = document.querySelector('.vm-alert-banner');
    const toggle   = document.getElementById('vmAlertToggle');
    const dropdown = document.getElementById('vmAlertDropdown');
    if (!banner || !dropdown) return;
    const isOpen = banner.classList.contains('vm-alert-open');
    banner.classList.toggle('vm-alert-open', !isOpen);
    toggle.setAttribute('aria-expanded', String(!isOpen));
    dropdown.setAttribute('aria-hidden', String(isOpen));
}

/* ─────────────────────────────────────────────
   COUNTDOWN TIMERS
   ───────────────────────────────────────────── */

function vmRenderCountdowns() {
    document.querySelectorAll('.vm-countdown[data-end]').forEach(vmTickCountdown);
}

function vmTickCountdown(el) {
    const endTime = new Date(el.getAttribute('data-end')).getTime();
    const diffMs  = endTime - Date.now();

    if (diffMs <= 0) {
        if (!el.hasAttribute('data-reloading')) {
            el.setAttribute('data-reloading', '1');
            const contact  = el.getAttribute('data-contact') || '';
            const callPart = contact ? `, or call <strong>${contact}</strong> for assistance` : '';
            el.innerHTML = `
                <span class="vm-countdown-badge vm-countdown-overdue">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;align-self:flex-start;margin-top:1px">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Past scheduled end — update expected shortly, thank you for your patience${callPart}</span>
                </span>`;
            setTimeout(function () {
                clearInterval(vmRefreshTimer);
                const url = new URL(location.href);
                url.searchParams.set('filter', vmActiveFilter);
                const q = document.getElementById('vmSearch')?.value || '';
                if (q) url.searchParams.set('q', q);
                location.replace(url.toString());
            }, 30000);
        }
        return;
    }

    const totalSecs = Math.floor(diffMs / 1000);
    const h = Math.floor(totalSecs / 3600);
    const m = Math.floor((totalSecs % 3600) / 60);
    const s = totalSecs % 60;

    const label   = h > 0 ? `Ends in ${h}h ${m}m` : m > 0 ? `Ends in ${m}m ${s}s` : `Ends in ${s}s`;
    const urgency = totalSecs < 300 ? 'vm-countdown-urgent' : totalSecs < 900 ? 'vm-countdown-soon' : 'vm-countdown-normal';

    el.innerHTML = `
        <span class="vm-countdown-badge ${urgency}">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            ${label}
        </span>`;
}

function vmStartCountdowns() {
    vmRenderCountdowns();
    setInterval(vmRenderCountdowns, 1000);
}

/* ─────────────────────────────────────────────
   FEATURE 3 — RELATIVE TIME on Done cards
   ───────────────────────────────────────────── */

function vmFormatRelativeTime(dateStr) {
    const now      = Date.now();
    const then     = new Date(dateStr).getTime();
    const diffSecs = Math.floor((now - then) / 1000);

    if (diffSecs < 60)                        return 'just now';
    if (diffSecs < 3600)  { const m = Math.floor(diffSecs / 60);   return `${m}m ago`; }
    if (diffSecs < 86400) { const h = Math.floor(diffSecs / 3600); return `${h}h ago`; }

    const days = Math.floor(diffSecs / 86400);
    if (days === 1)  return 'yesterday';
    if (days < 7)   return `${days} days ago`;
    if (days < 30)  { const w = Math.floor(days / 7);  return `${w} week${w > 1 ? 's' : ''} ago`; }
    if (days < 365) { const mo = Math.floor(days / 30); return `${mo} month${mo > 1 ? 's' : ''} ago`; }
    const yr = Math.floor(days / 365);
    return `${yr} year${yr > 1 ? 's' : ''} ago`;
}

function vmRenderRelativeTimes() {
    document.querySelectorAll('.vm-relative-time[data-updated]').forEach(el => {
        el.textContent = vmFormatRelativeTime(el.getAttribute('data-updated'));
    });
}

/* ─────────────────────────────────────────────
   FEATURE 4 — SEARCH HIGHLIGHT
   ───────────────────────────────────────────── */

function vmHighlightText(text, query) {
    if (!query) return escVm(text);
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex   = new RegExp(`(${escaped})`, 'gi');
    return escVm(text).replace(regex, '<mark class="vm-highlight">$1</mark>');
}

function vmApplyHighlights() {
    const query = vmActiveSearch;

    // Highlight system names
    document.querySelectorAll('.vm-system-name').forEach(el => {
        const original = el.getAttribute('title') || el.textContent;
        el.innerHTML = vmHighlightText(original, query);
    });

    // Highlight maintenance titles
    document.querySelectorAll('.vm-maint-title[data-original]').forEach(el => {
        const original = el.getAttribute('data-original');
        el.innerHTML = vmHighlightText(original, query);
    });
}

/* ─────────────────────────────────────────────
   SEARCH + FILTER
   ───────────────────────────────────────────── */

function vmApplyFilters() {
    const cards       = document.querySelectorAll('.vm-card');
    const filterEmpty = document.getElementById('vmFilterEmpty');
    const resultCount = document.getElementById('vmResultCount');
    let   visible     = 0;

    cards.forEach(card => {
        const cardStatus  = card.getAttribute('data-status') || '';
        const cardSearch  = card.getAttribute('data-search') || '';
        const matchFilter = vmActiveFilter === 'all' || cardStatus === vmActiveFilter;
        const matchSearch = vmActiveSearch === ''    || cardSearch.includes(vmActiveSearch);

        if (matchFilter && matchSearch) {
            card.classList.remove('vm-hidden');
            visible++;
        } else {
            card.classList.add('vm-hidden');
        }
    });

    vmApplyDonePagination();
    vmApplyHighlights();

    if (resultCount) {
        const statusLabels = { 'In Progress': 'in-progress', 'Scheduled': 'scheduled', 'Done': 'completed' };
        let txt = `Showing <strong>${visible}</strong>`;
        if (vmActiveFilter !== 'all') txt += ` ${statusLabels[vmActiveFilter] || vmActiveFilter}`;
        txt += ` maintenance record${visible !== 1 ? 's' : ''}`;
        if (vmActiveSearch) txt += ` matching "<em>${escVm(vmActiveSearch)}</em>"`;
        if (visible < VM_TOTAL) txt += ` <span class="vm-count-total">(${VM_TOTAL} total)</span>`;
        resultCount.innerHTML = txt;
    }

    if (filterEmpty) {
        if (visible === 0) {
            filterEmpty.style.display = 'block';
            const titleEl = document.getElementById('vmFilterEmptyTitle');
            const msgEl   = document.getElementById('vmFilterEmptyMsg');
            const emptyTitles = { 'In Progress': 'No Active Maintenance', 'Scheduled': 'Nothing Scheduled', 'Done': 'No Completed Records', 'all': 'No Records Found' };
            const emptyMsgs   = { 'In Progress': 'There are no systems currently under maintenance.', 'Scheduled': 'No maintenance windows are currently scheduled.', 'Done': 'No maintenance has been completed yet.', 'all': vmActiveSearch ? `No records match "${escVm(vmActiveSearch)}".` : 'No maintenance records available.' };
            if (titleEl) titleEl.textContent = emptyTitles[vmActiveFilter] || 'No Results Found';
            if (msgEl)   msgEl.textContent   = emptyMsgs[vmActiveFilter]   || 'No records match your filter.';
        } else {
            filterEmpty.style.display = 'none';
        }
    }

    const allClearBar = document.getElementById('vmAllClearBar');
    if (allClearBar) {
        allClearBar.style.display = (vmActiveFilter === 'all' || vmActiveFilter === 'Done') ? 'flex' : 'none';
    }
}

/* ─────────────────────────────────────────────
   DONE HISTORY PAGINATION
   ───────────────────────────────────────────── */

function vmApplyDonePagination() {
    if (vmActiveFilter !== 'all' && vmActiveFilter !== 'Done') return;

    const doneCards    = Array.from(document.querySelectorAll('.vm-card:not(.vm-hidden)')).filter(c => c.getAttribute('data-status') === 'Done');
    const showMoreBtn  = document.getElementById('vmShowMoreDone');
    const showMoreWrap = document.getElementById('vmShowMoreWrap');

    if (doneCards.length <= VM_DONE_PAGE_SIZE) {
        doneCards.forEach(c => c.classList.remove('vm-done-hidden'));
        if (showMoreWrap) showMoreWrap.style.display = 'none';
        return;
    }

    if (vmDoneShowAll) {
        doneCards.forEach(c => c.classList.remove('vm-done-hidden'));
        if (showMoreWrap) showMoreWrap.style.display = 'none';
    } else {
        doneCards.forEach((c, i) => {
            c.classList.toggle('vm-done-hidden', i >= VM_DONE_PAGE_SIZE);
        });
        if (showMoreWrap) {
            const hidden = doneCards.length - VM_DONE_PAGE_SIZE;
            showMoreWrap.style.display = 'flex';
            if (showMoreBtn) showMoreBtn.textContent = `Show ${hidden} older record${hidden !== 1 ? 's' : ''}`;
        }
    }
}

function vmToggleDoneHistory() {
    vmDoneShowAll = true;
    vmApplyDonePagination();
}

function vmFilter() {
    const input    = document.getElementById('vmSearch');
    vmActiveSearch = input ? input.value.trim().toLowerCase() : '';
    vmApplyFilters();
}

function vmSetFilter(status, btnEl) {
    vmActiveFilter = status;
    vmDoneShowAll  = false;
    // Sync desktop buttons
    document.querySelectorAll('.vm-filter-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-status') === status));
    // Sync sheet options
    document.querySelectorAll('.vm-sheet-option').forEach(b => b.classList.toggle('active', b.getAttribute('data-status') === status));
    // Update mobile trigger badge
    vmUpdateFilterSheetBadge(status);
    vmApplyFilters();
}

/* ─────────────────────────────────────────────
   FEATURE 6 — MOBILE BOTTOM SHEET
   ───────────────────────────────────────────── */

function vmOpenFilterSheet() {
    const sheet   = document.getElementById('vmBottomSheet');
    const overlay = document.getElementById('vmSheetOverlay');
    if (!sheet || !overlay) return;
    overlay.style.display = 'block';
    sheet.style.display   = 'block';
    // Force reflow then animate
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            overlay.classList.add('is-open');
            sheet.classList.add('is-open');
        });
    });
    document.body.style.overflow = 'hidden';
}

function vmCloseFilterSheet() {
    const sheet   = document.getElementById('vmBottomSheet');
    const overlay = document.getElementById('vmSheetOverlay');
    if (!sheet || !overlay) return;
    overlay.classList.remove('is-open');
    sheet.classList.remove('is-open');
    document.body.style.overflow = '';
    // Hide after transition
    setTimeout(() => {
        overlay.style.display = 'none';
        sheet.style.display   = 'none';
    }, 300);
}

function vmSheetFilter(status, btnEl) {
    vmSetFilter(status, btnEl);
    vmCloseFilterSheet();
}

function vmUpdateFilterSheetBadge(status) {
    const btn   = document.getElementById('vmFilterSheetBtn');
    const badge = document.getElementById('vmFilterSheetBadge');
    if (!btn || !badge) return;
    const isFiltered = status !== 'all';
    btn.classList.toggle('has-filter', isFiltered);
    badge.style.display = isFiltered ? 'block' : 'none';
    if (isFiltered) badge.title = `Filtering: ${status}`;
}

/* ─────────────────────────────────────────────
   RESTORE FILTER FROM URL
   ───────────────────────────────────────────── */

function vmRestoreState() {
    const params = new URLSearchParams(location.search);
    const filter = params.get('filter');
    const q      = params.get('q');

    if (filter && filter !== 'all') {
        vmActiveFilter = filter;
        document.querySelectorAll('.vm-filter-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-status') === filter));
        document.querySelectorAll('.vm-sheet-option').forEach(b => b.classList.toggle('active', b.getAttribute('data-status') === filter));
        vmUpdateFilterSheetBadge(filter);
    }
    if (q) {
        const input = document.getElementById('vmSearch');
        if (input) { input.value = q; vmActiveSearch = q.toLowerCase(); }
    }
    vmApplyFilters();
}

/* ─────────────────────────────────────────────
   KEYBOARD SHORTCUTS
   ───────────────────────────────────────────── */

document.addEventListener('keydown', function (e) {
    // Alert banner toggle
    const toggle = document.getElementById('vmAlertToggle');
    if (toggle && document.activeElement === toggle && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        vmToggleAlertDropdown();
    }
    // Search focus / clear
    const input = document.getElementById('vmSearch');
    if (e.key === 'Escape' && document.activeElement === input) {
        input.value = ''; vmActiveSearch = ''; vmApplyFilters(); input.blur();
    }
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        if (input) input.focus();
    }
    // Close sheet on Escape
    if (e.key === 'Escape') vmCloseFilterSheet();
});

/* ─────────────────────────────────────────────
   UTILS
   ───────────────────────────────────────────── */

function escVm(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

/* ─────────────────────────────────────────────
   INIT
   ───────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', function () {
    vmRestoreState();
    vmStartCountdowns();
    vmStartRefreshCycle();
    vmInitStickyControls();      // Feature 5
    vmRenderRelativeTimes();     // Feature 3 — initial render
    setInterval(vmRenderRelativeTimes, 60000); // update every minute
});