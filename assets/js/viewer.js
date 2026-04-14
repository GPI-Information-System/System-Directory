/* VIEWER PAGE JAVASCRIPT */

let currentFilter         = 'all';
let currentSearchTerm     = '';
let currentCategoryFilter = 'all';

const SLIDER_ICON_VIEWER = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <line x1="4" y1="6" x2="20" y2="6"></line>
    <line x1="4" y1="12" x2="20" y2="12"></line>
    <line x1="4" y1="18" x2="20" y2="18"></line>
    <circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle>
    <circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle>
    <circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle>
</svg>`;



function syncRecentsVisibility() {
    const recentsSection = document.getElementById('recentsSection');
    if (!recentsSection) return;
    const anyFilterActive = currentFilter !== 'all' || currentCategoryFilter !== 'all' || currentSearchTerm !== '';
    recentsSection.style.display = anyFilterActive ? 'none' : (getRecentIds().length > 0 ? 'block' : 'none');
}


function fetchMaintenanceBadges() {
    fetch('../backend/maintenance/get_maintenance.php?action=counts')
        .then(r => r.json())
        .then(data => { if (data.success) renderMaintenancePopover(data.in_progress, data.scheduled); })
        .catch(() => {});
}

function renderMaintenancePopover(inProgress, scheduled) {
    const popover = document.getElementById('maintPopover');
    const inner   = document.getElementById('maintPopoverInner');
    if (!popover || !inner) return;
    if (inProgress === 0 && scheduled === 0) { popover.style.display = 'none'; return; }
    let html = '';
    const jp = isJapanese;
    if (inProgress > 0) html += `<div class="maint-popover-row maint-popover-row--ongoing"><span class="maint-popover-dot maint-popover-dot--ongoing"></span><div class="maint-popover-text"><span class="maint-popover-count">${inProgress}</span><span class="maint-popover-label">${jp ? 'メンテナンス中' : 'Ongoing Maintenance'}</span></div></div>`;
    if (scheduled > 0) html += `<div class="maint-popover-row maint-popover-row--scheduled"><span class="maint-popover-dot maint-popover-dot--scheduled"></span><div class="maint-popover-text"><span class="maint-popover-count">${scheduled}</span><span class="maint-popover-label">${jp ? 'メンテナンス予定' : 'Scheduled Maintenance'}</span></div></div>`;
    inner.innerHTML = html;
    popover.style.display = 'block';
}


function toggleFilterViewer(event) {
    event.stopPropagation();
    const dropdown    = document.getElementById('statusDropdownViewer');
    const catDropdown = document.getElementById('categoryDropdownViewer');
    if (catDropdown) catDropdown.classList.remove('show');
    dropdown.classList.toggle('show');
    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target) && !event.target.closest('.filter-container-viewer')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function filterSystemsViewer(status) {
    currentFilter = status;
    syncRecentsVisibility();

    document.querySelectorAll('.filter-item[data-filter]').forEach(item => {
        item.classList.toggle('active', item.dataset.filter === status);
    });

    const statusBtn = document.getElementById('statusFilterBtn');
    if (statusBtn) {
        const labelMap = { 'all': 'Status Filter', 'online': 'Online', 'offline': 'Offline', 'maintenance': 'Maintenance', 'down': 'Down', 'archived': 'Archived' };
        statusBtn.innerHTML = SLIDER_ICON_VIEWER + (labelMap[status] || 'Status Filter');
        statusBtn.classList.toggle('btn-filter-viewer-active', status !== 'all');
    }

    const dropdown = document.getElementById('statusDropdownViewer');
    if (dropdown) dropdown.classList.remove('show');

    applyAllFilters();
}


function toggleCategoryFilterViewer(event) {
    event.stopPropagation();
    const dropdown       = document.getElementById('categoryDropdownViewer');
    const statusDropdown = document.getElementById('statusDropdownViewer');
    if (statusDropdown) statusDropdown.classList.remove('show');
    dropdown.classList.toggle('show');
    document.addEventListener('click', function closeCatDropdown(e) {
        if (!dropdown.contains(e.target) && !event.target.closest('.filter-container-viewer')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeCatDropdown);
        }
    });
}

function filterCategoryViewer(category) {
    currentCategoryFilter = category;
    syncRecentsVisibility();

    document.querySelectorAll('[data-cat]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.cat === category);
    });

    const catBtn = document.getElementById('categoryFilterBtn');
    if (catBtn) {
        const labelMap = { 'all': 'Category' };
        if (typeof DB_CATEGORIES !== 'undefined') {
            DB_CATEGORIES.forEach(name => { labelMap[name] = name; });
        }
        catBtn.innerHTML = SLIDER_ICON_VIEWER + (labelMap[category] || category || 'Category');
        catBtn.classList.toggle('btn-filter-viewer-active', category !== 'all');
    }

    const dropdown = document.getElementById('categoryDropdownViewer');
    if (dropdown) dropdown.classList.remove('show');

    applyAllFilters();
}


function searchSystemsViewer() {
    currentSearchTerm = document.getElementById('viewerSearchBox').value.toLowerCase();
    syncRecentsVisibility();
    applyAllFilters();
}


function applyAllFilters() {
    const cards = document.querySelectorAll('#viewerSystemsContainer .system-card-viewer');
    let visibleCount = 0;

    cards.forEach(card => {
        const cardStatus   = card.getAttribute('data-status') || 'online';
        const cardCategory = card.getAttribute('data-category') || '';
        const name         = card.querySelector('.system-name-viewer')?.textContent.toLowerCase() || '';
        const domain       = card.querySelector('.system-domain-viewer')?.textContent.toLowerCase() || '';
        const descEl       = card.querySelector('.system-description-viewer');
        const descText     = descEl ? descEl.textContent.toLowerCase() : '';

        const matchesSearch   = !currentSearchTerm || name.includes(currentSearchTerm) || domain.includes(currentSearchTerm) || descText.includes(currentSearchTerm);
        const matchesStatus   = currentFilter === 'all' || cardStatus === currentFilter;
        const matchesCategory = currentCategoryFilter === 'all' || cardCategory === currentCategoryFilter;

        if (cardStatus === 'archived' && currentFilter !== 'archived') {
            card.style.display = 'none';
        } else if (matchesStatus && matchesCategory && matchesSearch) {
            card.style.display = 'flex';
            card.style.opacity = cardStatus === 'archived' ? '0.5' : '1';
            card.style.pointerEvents = cardStatus === 'archived' ? 'none' : 'auto';
            card.style.cursor = cardStatus === 'archived' ? 'not-allowed' : 'pointer';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.querySelectorAll('.viewer-category-group').forEach(group => {
        const hasVisible = Array.from(group.querySelectorAll('.system-card-viewer'))
            .some(c => c.style.display !== 'none');
        group.style.display = hasVisible ? '' : 'none';
    });

    updateFilterDisplay(visibleCount);
    showEmptyState(visibleCount === 0, 'No systems match your filters');
}

function updateFilterDisplay(visibleCount) {
    const activeFiltersContainer = document.getElementById('activeFilters');
    const filterResultsText      = document.getElementById('filterResultsText');
    const clearFiltersBtn        = document.getElementById('clearFiltersBtn');
    const systemCount            = document.getElementById('systemCount');

    const hasActiveFilters = currentFilter !== 'all' || currentSearchTerm !== '' || currentCategoryFilter !== 'all';

    if (hasActiveFilters) {
        activeFiltersContainer.style.display = 'block';
        let filterText = `Showing ${visibleCount} of ${TOTAL_SYSTEMS}`;
        if (currentFilter !== 'all') {
            const labels = { online: 'online', offline: 'offline', maintenance: 'maintenance', down: 'down', archived: 'archived' };
            filterText += ` ${labels[currentFilter]}`;
        }
        if (currentCategoryFilter !== 'all') filterText += ` · ${currentCategoryFilter}`;
        filterText += currentSearchTerm ? ' matching search' : ' systems';
        filterResultsText.textContent = filterText;
        clearFiltersBtn.style.display = 'flex';
        systemCount.textContent = `(${visibleCount} of ${TOTAL_SYSTEMS})`;
    } else {
        activeFiltersContainer.style.display = 'none';
        clearFiltersBtn.style.display = 'none';
        systemCount.textContent = `(${TOTAL_SYSTEMS} total)`;
    }
}


function clearAllFilters() {
    document.getElementById('viewerSearchBox').value = '';
    currentSearchTerm     = '';
    currentFilter         = 'all';
    currentCategoryFilter = 'all';

    const statusBtn = document.getElementById('statusFilterBtn');
    if (statusBtn) { statusBtn.innerHTML = SLIDER_ICON_VIEWER + 'Status Filter'; statusBtn.classList.remove('btn-filter-viewer-active'); }

    const catBtn = document.getElementById('categoryFilterBtn');
    if (catBtn) { catBtn.innerHTML = SLIDER_ICON_VIEWER + 'Category'; catBtn.classList.remove('btn-filter-viewer-active'); }

    document.querySelectorAll('.filter-item[data-filter]').forEach(item => item.classList.toggle('active', item.dataset.filter === 'all'));
    document.querySelectorAll('#categoryDropdownViewer .filter-item').forEach(item => item.classList.toggle('active', item.dataset.cat === 'all'));

    document.querySelectorAll('.viewer-category-group').forEach(g => g.style.display = '');

    const cards = document.querySelectorAll('#viewerSystemsContainer .system-card-viewer');
    let activeCount = 0;
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'online';
        if (cardStatus === 'archived') { card.style.display = 'none'; }
        else { card.style.display = 'flex'; card.style.opacity = '1'; card.style.pointerEvents = 'auto'; card.style.cursor = 'pointer'; activeCount++; }
    });

    syncRecentsVisibility();
    updateFilterDisplay(activeCount);
    showEmptyState(false);
}


function showEmptyState(show, message = 'No systems found') {
    const container = document.getElementById('viewerSystemsContainer');
    let emptyState  = container.querySelector('.filter-empty-state');
    if (show) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state-viewer filter-empty-state';
            emptyState.innerHTML = `<svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg><h3>No Systems Found</h3><p>${message}</p>`;
            container.appendChild(emptyState);
        } else { emptyState.querySelector('p').textContent = message; }
    } else { if (emptyState) emptyState.remove(); }
}



function openDomainViewer(cardEl) {
    const card = cardEl.closest ? cardEl.closest('.system-card-viewer') : cardEl;
    if (!card) return;

    const cardStatus = card.getAttribute('data-status') || 'online';
    const domainEl   = card.querySelector('.system-domain-viewer');

    // English domain
    const originalDomain = domainEl
        ? (domainEl.getAttribute('data-en-domain') || domainEl.textContent.trim())
        : '';

    // Japanese domain & network type
    const jpDomain    = card.getAttribute('data-japanese-domain') || '';
    const networkType = card.getAttribute('data-network-type') || 'https';
    const isJp        = !!(isJapanese && jpDomain);
    const domain      = isJp ? jpDomain : originalDomain;

    if (['maintenance', 'down', 'offline'].includes(cardStatus)) {
        let url = '../pages/error_page.php?type=' + encodeURIComponent(cardStatus) + '&domain=' + encodeURIComponent(originalDomain);
        if (isJp) url += '&display_domain=' + encodeURIComponent(jpDomain);
        window.location.href = url;
        return;
    }

    let url = domain;
    if (!domain.startsWith('http://') && !domain.startsWith('https://')) {
        url = (isJp ? 'http://' : (networkType === 'http' ? 'http://' : 'https://')) + domain;
    }
    window.open(url, '_blank');
}


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('statusDropdownViewer')?.classList.remove('show');
        document.getElementById('categoryDropdownViewer')?.classList.remove('show');
        const loginBtn = document.querySelector('.login-button-slide');
        if (loginBtn?.classList.contains('show')) loginBtn.classList.remove('show');
    }
});


document.addEventListener('DOMContentLoaded', function() {
    let activeCount = 0;
    document.querySelectorAll('#viewerSystemsContainer .system-card-viewer').forEach(card => {
        if ((card.getAttribute('data-status') || '') === 'archived') card.style.display = 'none';
        else activeCount++;
    });
    updateFilterDisplay(activeCount);
    fetchMaintenanceBadges();
    initJapaneseToggle();
});


/* JAPANESE TRANSLATIONS  */

const JP_TRANSLATIONS = {
    status: {
        'Online':      'オンライン',
        'Offline':     'オフライン',
        'Maintenance': 'メンテナンス',
        'Down':        'ダウン',
        'Archived':    'アーカイブ済み',
    },
    category: {
        'Direct Systems':   'ダイレクトシステム',
        'Indirect Systems': 'インダイレクトシステム',
        'Support Systems':  'サポートシステム',
    },
    contact:        '支援のために {number} にお問い合わせください',
    pageTitle:      'システムディレクトリ',
    pageSubtitle:   '利用可能なすべてのシステムを参照',
    maintenance:    'メンテナンススケジュール',
    statusFilter:   'ステータスフィルター',
    categoryFilter: 'カテゴリー',
    statusLogs:     'ステータスログ',
    recentsTitle:   '最近',
    recentsClear:   'クリア',
    recentBadge:    '最近',
    systemsTitle:   'システム',
};

let isJapanese = false;

function initJapaneseToggle() {
    isJapanese = localStorage.getItem('gportal_jp_mode') === 'true';
    updateToggleUI();
    if (isJapanese) applyJapaneseTranslation();
}

function setLanguage(lang) {
    isJapanese = (lang === 'jp');
    localStorage.setItem('gportal_jp_mode', isJapanese ? 'true' : 'false');
    updateToggleUI();
    if (isJapanese) applyJapaneseTranslation();
    else revertToEnglish();
    applyAllFilters();
    renderRecents();
}

function toggleJapanese() {
    setLanguage(isJapanese ? 'en' : 'jp');
}

function updateToggleUI() {
    const engBtn = document.getElementById('jpLangEng');
    const jpBtn  = document.getElementById('jpLangJp');
    if (!engBtn || !jpBtn) return;
    engBtn.classList.toggle('active', !isJapanese);
    jpBtn.classList.toggle('active', isJapanese);
}

function translateAllDescriptions() {
    document.querySelectorAll('#viewerSystemsContainer .system-card-viewer').forEach(card => {
        const desc = card.querySelector('.system-description-viewer');
        if (!desc) return;
        if (!desc.getAttribute('data-en-desc')) {
            desc.setAttribute('data-en-desc', desc.textContent.trim());
        }
        const jpDesc = card.getAttribute('data-japanese-description') || '';
        if (jpDesc) desc.textContent = jpDesc;
    });
}

function revertAllDescriptions() {
    document.querySelectorAll('#viewerSystemsContainer .system-card-viewer').forEach(card => {
        const desc = card.querySelector('.system-description-viewer');
        if (!desc) return;
        const en = desc.getAttribute('data-en-desc');
        if (en !== null) desc.textContent = en;
    });
}

function applyJapaneseTranslation() {
    const pageTitle = document.querySelector('.page-title');
    if (pageTitle && !pageTitle.getAttribute('data-en')) {
        pageTitle.setAttribute('data-en', pageTitle.textContent.trim());
    }
    if (pageTitle) pageTitle.textContent = JP_TRANSLATIONS.pageTitle;

    const pageSubtitle = document.querySelector('.page-subtitle');
    if (pageSubtitle) {
        if (!pageSubtitle.getAttribute('data-en-text')) {
            pageSubtitle.setAttribute('data-en-text', 'Browse all available systems');
        }
        pageSubtitle.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                node.textContent = JP_TRANSLATIONS.pageSubtitle + ' ';
            }
        });
    }

    const maintBtn = document.querySelector('.btn-maintenance-viewer');
    if (maintBtn && !maintBtn.getAttribute('data-en')) {
        maintBtn.setAttribute('data-en', maintBtn.textContent.trim());
    }
    if (maintBtn) {
        const svg = maintBtn.querySelector('svg');
        maintBtn.innerHTML = '';
        if (svg) maintBtn.appendChild(svg);
        maintBtn.appendChild(document.createTextNode(' ' + JP_TRANSLATIONS.maintenance));
    }

    const statusFilterBtn = document.getElementById('statusFilterBtn');
    if (statusFilterBtn) {
        const currentText = statusFilterBtn.textContent.trim();
        const statusLabelsJp = {
            'Status Filter': 'ステータスフィルター',
            'Online': 'オンライン', 'Offline': 'オフライン',
            'Maintenance': 'メンテナンス', 'Down': 'ダウン', 'Archived': 'アーカイブ済み'
        };
        statusFilterBtn.setAttribute('data-en-label', currentText);
        statusFilterBtn.innerHTML = SLIDER_ICON_VIEWER + (statusLabelsJp[currentText] || currentText);
    }

    const catBtn = document.getElementById('categoryFilterBtn');
    if (catBtn) {
        const currentText = catBtn.textContent.trim();
        const catLabelsJp = {
            'Category': 'カテゴリー',
            'Direct': 'ダイレクト', 'Indirect': 'インダイレクト', 'Support': 'サポート'
        };
        catBtn.setAttribute('data-en-label', currentText);
        catBtn.innerHTML = SLIDER_ICON_VIEWER + (catLabelsJp[currentText] || currentText);
    }

    const statusLogsBtn = document.querySelector('.btn-status-logs-viewer');
    if (statusLogsBtn && !statusLogsBtn.getAttribute('data-en')) {
        statusLogsBtn.setAttribute('data-en', statusLogsBtn.textContent.trim());
    }
    if (statusLogsBtn) {
        const svg = statusLogsBtn.querySelector('svg');
        statusLogsBtn.innerHTML = '';
        if (svg) statusLogsBtn.appendChild(svg);
        statusLogsBtn.appendChild(document.createTextNode(' ' + JP_TRANSLATIONS.statusLogs));
    }

    const searchBox = document.getElementById('viewerSearchBox');
    if (searchBox) {
        searchBox.setAttribute('data-en-placeholder', searchBox.placeholder);
        searchBox.placeholder = 'システムを検索...';
    }

    const statusOptionMap = {
        'All Systems': '全システム', 'Online': 'オンライン', 'Offline': 'オフライン',
        'Maintenance': 'メンテナンス', 'Down': 'ダウン', 'Archived': 'アーカイブ済み',
    };
    document.querySelectorAll('#statusDropdownViewer .filter-item').forEach(item => {
        const textNode = Array.from(item.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) {
            if (!item.getAttribute('data-en-text')) item.setAttribute('data-en-text', textNode.textContent.trim());
            textNode.textContent = ' ' + (statusOptionMap[textNode.textContent.trim()] || textNode.textContent.trim());
        }
    });

    const categoryOptionMap = {
        'All Categories':   '全カテゴリー',
        'Direct Systems':   'ダイレクトシステム',
        'Indirect Systems': 'インダイレクトシステム',
        'Support Systems':  'サポートシステム',
    };
    document.querySelectorAll('#categoryDropdownViewer .filter-item').forEach(item => {
        const textNode = Array.from(item.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) {
            if (!item.getAttribute('data-en-text')) item.setAttribute('data-en-text', textNode.textContent.trim());
            textNode.textContent = ' ' + (categoryOptionMap[textNode.textContent.trim()] || textNode.textContent.trim());
        }
    });

    fetchMaintenanceBadges();
    if (typeof renderNotifications === 'function') renderNotifications(allNotifications);

    document.querySelectorAll('.viewer-category-title').forEach(el => {
        const original = el.getAttribute('data-en') || el.textContent.trim();
        el.setAttribute('data-en', original);
        el.textContent = JP_TRANSLATIONS.category[original] || original;
    });

    document.querySelectorAll('#viewerSystemsContainer .system-card-viewer').forEach(card => {
        const jpDomain   = card.getAttribute('data-japanese-domain') || '';
        const mainDomain = card.querySelector('.system-domain-viewer');
        if (mainDomain) {
            if (!mainDomain.getAttribute('data-en-domain')) {
                mainDomain.setAttribute('data-en-domain', mainDomain.textContent.trim());
            }
            if (jpDomain) mainDomain.textContent = jpDomain;
        }

        const badge = card.querySelector('.status-badge-viewer');
        if (badge) {
            const dot    = badge.querySelector('.status-indicator-viewer');
            const enText = badge.getAttribute('data-en-status') || badge.textContent.trim();
            badge.setAttribute('data-en-status', enText);
            const jpText = JP_TRANSLATIONS.status[enText] || enText;
            badge.innerHTML = '';
            if (dot) badge.appendChild(dot.cloneNode(true));
            badge.appendChild(document.createTextNode(jpText));
        }

        const desc = card.querySelector('.system-description-viewer');
        if (desc) {
            if (!desc.getAttribute('data-en-desc')) {
                desc.setAttribute('data-en-desc', desc.textContent.trim());
            }
            const jpDesc = card.getAttribute('data-japanese-description') || '';
            if (jpDesc) desc.textContent = jpDesc;
        }

        const contact = card.querySelector('.system-contact-message');
        if (contact) {
            const number = card.getAttribute('data-contact-number') || '123';
            if (!contact.getAttribute('data-en-html')) {
                contact.setAttribute('data-en-html', contact.innerHTML);
            }
            contact.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                ${JP_TRANSLATIONS.contact.replace('{number}', `<span class="contact-number">${number}</span>`)}
            `;
        }
    });

    // Recents title
    const recentsTitle = document.querySelector('.recents-title');
    if (recentsTitle) {
        if (!recentsTitle.getAttribute('data-en')) recentsTitle.setAttribute('data-en', recentsTitle.textContent.trim());
        recentsTitle.textContent = JP_TRANSLATIONS.recentsTitle;
    }

    // Recents clear button
    const recentsClearBtn = document.querySelector('.recents-clear-btn');
    if (recentsClearBtn) {
        const textNode = Array.from(recentsClearBtn.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) {
            if (!recentsClearBtn.getAttribute('data-en-clear')) recentsClearBtn.setAttribute('data-en-clear', textNode.textContent.trim());
            textNode.textContent = ' ' + JP_TRANSLATIONS.recentsClear;
        }
    }

    // Systems section title
    const sectionTitle = document.querySelector('.viewer-section-title');
    if (sectionTitle) {
        if (!sectionTitle.getAttribute('data-en')) sectionTitle.setAttribute('data-en', sectionTitle.textContent.trim());
        sectionTitle.textContent = JP_TRANSLATIONS.systemsTitle;
    }
}

function revertToEnglish() {
    revertAllDescriptions();
    fetchMaintenanceBadges();

    const pageTitle = document.querySelector('.page-title');
    if (pageTitle) { const en = pageTitle.getAttribute('data-en'); if (en) pageTitle.textContent = en; }

    const pageSubtitle = document.querySelector('.page-subtitle');
    if (pageSubtitle) {
        pageSubtitle.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                node.textContent = 'Browse all available systems ';
            }
        });
    }

    const maintBtn = document.querySelector('.btn-maintenance-viewer');
    if (maintBtn) {
        const en = maintBtn.getAttribute('data-en');
        const svg = maintBtn.querySelector('svg');
        if (en && svg) { maintBtn.innerHTML = ''; maintBtn.appendChild(svg); maintBtn.appendChild(document.createTextNode(' ' + en.trim())); }
    }

    const statusFilterBtn = document.getElementById('statusFilterBtn');
    if (statusFilterBtn) {
        const en = statusFilterBtn.getAttribute('data-en-label') || 'Status Filter';
        statusFilterBtn.innerHTML = SLIDER_ICON_VIEWER + en;
    }

    const catBtn = document.getElementById('categoryFilterBtn');
    if (catBtn) {
        const en = catBtn.getAttribute('data-en-label') || 'Category';
        catBtn.innerHTML = SLIDER_ICON_VIEWER + en;
    }

    const statusLogsBtn = document.querySelector('.btn-status-logs-viewer');
    if (statusLogsBtn) {
        const en = statusLogsBtn.getAttribute('data-en');
        const svg = statusLogsBtn.querySelector('svg');
        if (en && svg) { statusLogsBtn.innerHTML = ''; statusLogsBtn.appendChild(svg); statusLogsBtn.appendChild(document.createTextNode(' ' + en.trim())); }
    }

    const searchBox = document.getElementById('viewerSearchBox');
    if (searchBox) { const en = searchBox.getAttribute('data-en-placeholder'); if (en) searchBox.placeholder = en; }

    document.querySelectorAll('#statusDropdownViewer .filter-item').forEach(item => {
        const textNode = Array.from(item.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) { const en = item.getAttribute('data-en-text'); if (en) textNode.textContent = ' ' + en; }
    });

    document.querySelectorAll('#categoryDropdownViewer .filter-item').forEach(item => {
        const textNode = Array.from(item.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) { const en = item.getAttribute('data-en-text'); if (en) textNode.textContent = ' ' + en; }
    });

    document.querySelectorAll('.viewer-category-title').forEach(el => {
        const en = el.getAttribute('data-en'); if (en) el.textContent = en;
    });

    document.querySelectorAll('#viewerSystemsContainer .system-card-viewer').forEach(card => {
        const mainDomain = card.querySelector('.system-domain-viewer');
        if (mainDomain) { const en = mainDomain.getAttribute('data-en-domain'); if (en) mainDomain.textContent = en; }

        const badge = card.querySelector('.status-badge-viewer');
        if (badge) {
            const dot    = badge.querySelector('.status-indicator-viewer');
            const enText = badge.getAttribute('data-en-status');
            if (enText) { badge.innerHTML = ''; if (dot) badge.appendChild(dot.cloneNode(true)); badge.appendChild(document.createTextNode(enText)); }
        }

        const contact = card.querySelector('.system-contact-message');
        if (contact) { const enHtml = contact.getAttribute('data-en-html'); if (enHtml) contact.innerHTML = enHtml; }
    });

    // Recents title
    const recentsTitle = document.querySelector('.recents-title');
    if (recentsTitle) { const en = recentsTitle.getAttribute('data-en'); if (en) recentsTitle.textContent = en; }

    // Recents clear button
    const recentsClearBtn = document.querySelector('.recents-clear-btn');
    if (recentsClearBtn) {
        const textNode = Array.from(recentsClearBtn.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
        if (textNode) { const en = recentsClearBtn.getAttribute('data-en-clear'); if (en) textNode.textContent = ' ' + en; }
    }

    // Systems section title
    const sectionTitle = document.querySelector('.viewer-section-title');
    if (sectionTitle) { const en = sectionTitle.getAttribute('data-en'); if (en) sectionTitle.textContent = en; }
}


/* ── RECENTS (Cookie-based, max 5 systems) */

const RECENTS_COOKIE = 'gportal_recents';
const RECENTS_MAX    = 5;
const RECENTS_DAYS   = 30;

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) +
        '; expires=' + expires + '; path=/; SameSite=Lax';
}

function getCookie(name) {
    const match = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
    return match ? decodeURIComponent(match[1]) : null;
}

function deleteCookie(name) {
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

function getRecentIds() {
    try {
        const raw = getCookie(RECENTS_COOKIE);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function saveRecentId(systemId) {
    const id = String(systemId);
    let ids  = getRecentIds().filter(x => x !== id);
    ids.unshift(id);
    ids = ids.slice(0, RECENTS_MAX);
    setCookie(RECENTS_COOKIE, JSON.stringify(ids), RECENTS_DAYS);
}

function renderRecents() {
    const section = document.getElementById('recentsSection');
    const grid    = document.getElementById('recentsGrid');
    if (!section || !grid) return;

    const ids = getRecentIds();
    if (ids.length === 0) {
        section.style.display = 'none';
        return;
    }

    grid.innerHTML = '';

    let rendered = 0;
    ids.forEach(id => {
        const source = document.querySelector(
            '#viewerSystemsContainer .system-card-viewer[data-system-id="' + id + '"]'
        );
        if (!source) return;

        const clone = source.cloneNode(true);
        clone.style.display       = 'flex';
        clone.style.opacity       = '1';
        clone.style.pointerEvents = 'auto';
        clone.style.cursor        = 'pointer';

        const wrapper = document.createElement('div');
        wrapper.className = 'recents-card-wrapper';

        const badge = document.createElement('span');
        badge.className   = 'recents-badge';
        badge.textContent = isJapanese ? JP_TRANSLATIONS.recentBadge : 'RECENT';

        wrapper.appendChild(badge);
        wrapper.appendChild(clone);
        grid.appendChild(wrapper);
        rendered++;
    });

    section.style.display = rendered > 0 ? 'block' : 'none';
}

function clearRecents() {
    deleteCookie(RECENTS_COOKIE);
    const section = document.getElementById('recentsSection');
    if (section) section.style.display = 'none';
}

(function () {
    const _original = openDomainViewer;

    openDomainViewer = function (cardEl) {
        const card = (cardEl && cardEl.closest)
            ? cardEl.closest('.system-card-viewer')
            : null;

        if (!card) return;

        if (!card.closest('#recentsGrid')) {
            const id = card.getAttribute('data-system-id');
            if (id) saveRecentId(id);
        }

        _original.call(this, card);
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    renderRecents();
});