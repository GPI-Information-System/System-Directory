// Global variables
let currentEditId = null;
let deleteSystemId = null;
let deleteSystemName = null;

// Close dropdowns 
document.addEventListener("click", function (e) {
  if (!e.target.closest(".card-menu")) {
    closeAllDropdowns();
  }
});

function toggleDropdown(event, cardId) {
  event.stopPropagation();
  const dropdown = event.currentTarget.nextElementSibling;
  const isCurrentlyOpen = dropdown.classList.contains("show");
  closeAllDropdowns();
  if (!isCurrentlyOpen) {
    dropdown.classList.add("show");
  }
}

function closeAllDropdowns() {
  document.querySelectorAll(".dropdown-menu").forEach((menu) => menu.classList.remove("show"));
  document.querySelectorAll(".filter-dropdown-menu").forEach((menu) => menu.classList.remove("show"));
}

//  NETWORK TYPE 
function selectNetworkType(modal, value) {
  const segmentId = modal === 'add' ? 'addNetworkTypeSegment' : 'editNetworkTypeSegment';
  const hiddenId  = modal === 'add' ? 'addNetworkTypeValue'   : 'editNetworkTypeValue';

  const segment = document.getElementById(segmentId);
  const hidden  = document.getElementById(hiddenId);
  if (!segment || !hidden) return;

  segment.querySelectorAll('.segment-btn').forEach(btn => {
    btn.classList.toggle('segment-active', btn.getAttribute('data-value') === value);
  });
  hidden.value = value;
  segment.classList.remove('segment-error');
}

// Open Add System Modal 
function openAddModal() {
  document.getElementById("addModal").classList.add("show");
  document.getElementById("addSystemForm").reset();
  const nameInput = document.getElementById("systemName");
  if (nameInput) updateCharCounter(nameInput, "addNameCounter", 100);
  const addBox = document.getElementById("addLogoPreviewBox");
  if (addBox) addBox.classList.remove("visible");

  // Initialize status 
  const statusSelect = document.getElementById("systemStatus");
  const statusDot    = document.getElementById("addStatusDot");
  if (statusSelect && statusDot) {
    statusDot.style.background = STATUS_DOT_COLORS[statusSelect.value] || "#9CA3AF";
  }

  // Reset network type 
  const seg = document.getElementById('addNetworkTypeSegment');
  const hid = document.getElementById('addNetworkTypeValue');
  if (seg) seg.querySelectorAll('.segment-btn').forEach(b => b.classList.remove('segment-active'));
  if (hid) hid.value = '';
  if (seg) seg.classList.remove('segment-error');
}

function closeAddModal() {
  document.getElementById("addModal").classList.remove("show");
  document.getElementById("addSystemForm").reset();
}

// ── OPEN Edit System Modal 
function openEditModal(
  id,
  name,
  domain,
  badgeUrl,
  description,
  status,
  contactNumber,
  excludeHealthCheck = 0,
  logoPath = "",
  category = "Direct",
  japaneseDomain = "",
  japaneseDescription = "",
  networkType = "https"
) {
  currentEditId = id;

  const card       = document.querySelector('.system-card[data-system-id="' + id + '"]');
  const liveStatus = card ? card.dataset.status || status : status;

  document.getElementById("editModal").classList.add("show");
  document.getElementById("editSystemName").value        = name;
  const editCatSel = document.getElementById("editSystemCategory");
  if (editCatSel) editCatSel.value = category || "Direct";
  const editJpDomain = document.getElementById("editSystemJapaneseDomain");
  if (editJpDomain) editJpDomain.value = japaneseDomain || "";
  const editJpDesc = document.getElementById("editSystemJapaneseDescription");
  if (editJpDesc) editJpDesc.value = japaneseDescription || "";
  document.getElementById("editSystemDomain").value      = domain;
  document.getElementById("editSystemBadgeUrl").value    = badgeUrl || "";
  document.getElementById("editSystemDescription").value = description;
  document.getElementById("editSystemStatus").value      = liveStatus || "online";
  document.getElementById("editSystemContact").value     = contactNumber || "123";
  document.getElementById("editExcludeHealthCheck").checked = excludeHealthCheck == 1;

  const statusSelect = document.getElementById("editSystemStatus");
  const statusDot    = document.getElementById("editStatusDot");
  if (statusSelect && statusDot) {
    statusDot.style.background = STATUS_DOT_COLORS[statusSelect.value] || "#9CA3AF";
  }

  const nameInput = document.getElementById("editSystemName");
  if (nameInput) updateCharCounter(nameInput, "editNameCounter", 100);

  // Logo preview
  const editBox   = document.getElementById("editLogoPreviewBox");
  const editThumb = document.getElementById("editLogoThumb");
  const editFname = document.getElementById("editLogoFilename");
  const editFsize = document.getElementById("editLogoFilesize");
  const editInput = document.getElementById("editSystemLogo");
  if (logoPath && editBox && editThumb) {
    editThumb.src = logoPath;
    if (editFname) editFname.textContent = logoPath.split("/").pop();
    if (editFsize) editFsize.textContent = "Current logo";
    editBox.classList.add("visible");
  } else {
    if (editBox) editBox.classList.remove("visible");
    if (editInput) editInput.value = "";
  }

  // Pre-select network type 
  selectNetworkType('edit', networkType || 'https');
}

function closeEditModal() {
  document.getElementById("editModal").classList.remove("show");
  document.getElementById("editSystemForm").reset();
  currentEditId = null;
}

// Add System 
function addSystem(event) {
  event.preventDefault();

  // Validate network type
  const networkTypeVal = document.getElementById('addNetworkTypeValue')?.value;
  if (!networkTypeVal) {
    const seg = document.getElementById('addNetworkTypeSegment');
    if (seg) {
      seg.classList.add('segment-error');
      seg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    showModalError('addModal', 'Please select a Network Type (HTTP or HTTPS).');
    return;
  }

  const formData = new FormData(event.target);
  if (!formData.get("contact_number")) {
    formData.set("contact_number", "123");
  }
  clearModalError("addModal");
  fetch("../backend/add_system.php", { method: "POST", body: formData })
    .then((response) => response.text())
    .then((text) => {
      if (!text || text.trim() === "") {
        showModalError("addModal", "Logo file is too large. Maximum allowed size is 5 MB. Please resize or compress your image and try again.");
        return;
      }
      let data;
      try { data = JSON.parse(text); } catch (e) {
        showModalError("addModal", "An unexpected error occurred. Please try again.");
        return;
      }
      if (data.success) {
        closeAddModal();
        location.reload();
      } else {
        showModalError("addModal", data.message || "An error occurred. Please try again.");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showModalError("addModal", "A connection error occurred. Please check your network and try again.");
    });
}

// Edit System 
function editSystem(event) {
  event.preventDefault();

  // Validate network type
  const networkTypeVal = document.getElementById('editNetworkTypeValue')?.value;
  if (!networkTypeVal) {
    const seg = document.getElementById('editNetworkTypeSegment');
    if (seg) {
      seg.classList.add('segment-error');
      seg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    showModalError('editModal', 'Please select a Network Type (HTTP or HTTPS).');
    return;
  }

  const formData = new FormData(event.target);
  formData.append("id", currentEditId);
  if (!formData.get("contact_number")) {
    formData.set("contact_number", "123");
  }
  clearModalError("editModal");
  fetch("../backend/edit_system.php", { method: "POST", body: formData })
    .then((response) => response.text())
    .then((text) => {
      if (!text || text.trim() === "") {
        showModalError("editModal", "Logo file is too large. Maximum allowed size is 5 MB. Please resize or compress your image and try again.");
        return;
      }
      let data;
      try { data = JSON.parse(text); } catch (e) {
        showModalError("editModal", "An unexpected error occurred. Please try again.");
        return;
      }
      if (data.success) {
        closeEditModal();
        location.reload();
      } else {
        showModalError("editModal", data.message || "An error occurred. Please try again.");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showModalError("editModal", "A connection error occurred. Please check your network and try again.");
    });
}

// Delete System 
function deleteSystem(id, name) {
  deleteSystemId = id;
  deleteSystemName = name;
  document.getElementById("deleteSystemName").textContent = name;
  document.getElementById("deleteModal").classList.add("show");
}

function confirmDelete() {
  if (!deleteSystemId) return;
  const formData = new FormData();
  formData.append("id", deleteSystemId);
  fetch("../backend/delete_system.php", { method: "POST", body: formData })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        closeDeleteModal();
        location.reload();
      } else {
        showModalError("deleteModal", data.message || "An error occurred. Please try again.");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showModalError("deleteModal", "A network error occurred. Please try again.");
    });
}

function closeDeleteModal() {
  document.getElementById("deleteModal").classList.remove("show");
  deleteSystemId = null;
  deleteSystemName = null;
}

//  Search 
function searchSystems() {
  const searchTerm = document.getElementById("searchBox").value.toLowerCase();
  const cards = document.querySelectorAll(".system-card");
  cards.forEach((card) => {
    const name        = card.querySelector(".card-title").textContent.toLowerCase();
    const domain      = card.querySelector(".card-domain").textContent.toLowerCase();
    const description = card.querySelector(".card-description")?.textContent.toLowerCase() || "";
    card.style.display =
      name.includes(searchTerm) || domain.includes(searchTerm) || description.includes(searchTerm)
        ? "block" : "none";
  });

  // Hide category 
  document.querySelectorAll(".category-group").forEach((group) => {
    const hasVisible = Array.from(group.querySelectorAll(".system-card"))
      .some(c => c.style.display !== "none");
    group.style.display = hasVisible ? "" : "none";
  });
}

// ── open Domain —uses stored network type 
function openDomain(systemId) {
  if (!systemId) return;

  const card = document.querySelector('.system-card[data-system-id="' + systemId + '"]');
  if (!card) return;

  const domainEl    = card.querySelector('.card-domain');
  const domain      = domainEl ? domainEl.textContent.trim() : '';
  if (!domain) return;

  const cardStatus     = card.getAttribute('data-status') || 'online';
  const japaneseDomain = card.getAttribute('data-japanese-domain') || '';
  const networkType    = card.getAttribute('data-network-type') || 'https';

  if (cardStatus === 'maintenance' || cardStatus === 'down' || cardStatus === 'offline') {
    const engDomain     = card.getAttribute('data-eng-domain') || domain;
    const displayDomain = (japaneseDomainActive && japaneseDomain) ? japaneseDomain : engDomain;
    window.location.href =
      '../pages/error_page.php?type=' + encodeURIComponent(cardStatus) +
      '&domain=' + encodeURIComponent(engDomain) +
      '&system_id=' + encodeURIComponent(systemId) +
      '&display_domain=' + encodeURIComponent(displayDomain) +
      '&from=dashboard';
    return;
  }

  let url = domain;
  if (!domain.startsWith('http://') && !domain.startsWith('https://')) {
    url = (networkType === 'http' ? 'http://' : 'https://') + domain;
  }
  window.open(url, '_blank');
}

window.onclick = function (event) {
  const addModal    = document.getElementById("addModal");
  const editModal   = document.getElementById("editModal");
  const deleteModal = document.getElementById("deleteModal");
  if (event.target === addModal)    closeAddModal();
  if (event.target === editModal)   closeEditModal();
  if (event.target === deleteModal) closeDeleteModal();
};

//  Filter 
function updateClearFiltersBtn() {
  const btn = document.getElementById("btnClearFilters");
  if (!btn) return;
  const statusActive   = document.querySelector("[data-filter].active")?.dataset.filter !== "all";
  const categoryActive = document.querySelector("[data-cat].active")?.dataset.cat !== "all";
  btn.style.display = statusActive || categoryActive ? "flex" : "none";
}

function clearAllFilters() {
  filterSystems("all");
  filterByCategory("all");
  const btn = document.getElementById("btnClearFilters");
  if (btn) btn.style.display = "none";
}

function toggleCategoryDropdown(event) {
  event.stopPropagation();
  const menu       = document.getElementById("categoryFilterMenu");
  const statusMenu = document.querySelector(".filter-dropdown-menu.show:not(#categoryFilterMenu)");
  if (statusMenu) statusMenu.classList.remove("show");
  menu.classList.toggle("show");
}

function filterByCategory(category) {
  const catBtn = document.querySelector('.btn-filter[onclick="toggleCategoryDropdown(event)"]');
  if (catBtn) {
    const labelMap = { all: "Category", Direct: "Direct", Indirect: "Indirect", Support: "Support" };
    const label    = labelMap[category] || "Category";
    const isFiltered = category !== "all";
    catBtn.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line>
        <circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle>
        <circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle>
        <circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle>
      </svg>${label}`;
    catBtn.classList.toggle("btn-filter-active", isFiltered);
  }
  document.querySelectorAll("[data-cat]").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.cat === category);
  });
  document.getElementById("categoryFilterMenu").classList.remove("show");
  updateClearFiltersBtn();
  const groups = document.querySelectorAll(".category-group");
  groups.forEach((group) => {
    group.style.display = category === "all" || group.dataset.category === category ? "" : "none";
  });
}

function toggleFilterDropdown(event) {
  event.stopPropagation();
  const dropdown = event.currentTarget.nextElementSibling;
  dropdown.classList.toggle("show");
  document.addEventListener("click", function closeDropdown(e) {
    if (!dropdown.contains(e.target) && !event.currentTarget.contains(e.target)) {
      dropdown.classList.remove("show");
      document.removeEventListener("click", closeDropdown);
    }
  });
}

function filterSystems(status) {
  const filterBtn = document.querySelector('.btn-filter[onclick="toggleFilterDropdown(event)"]');
  if (filterBtn) {
    const labelMap = { all: "Status Filter", online: "Online", offline: "Offline", maintenance: "Maintenance", down: "Down", archived: "Archived" };
    const label    = labelMap[status] || "Status Filter";
    filterBtn.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line>
        <circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle>
        <circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle>
        <circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle>
      </svg>${label}`;
    filterBtn.classList.toggle("btn-filter-active", status !== "all");
  }
  updateClearFiltersBtn();
  const cards = document.querySelectorAll(".system-card");
  document.querySelectorAll(".filter-option").forEach((option) => {
    option.classList.remove("active");
    if (option.getAttribute("data-filter") === status) option.classList.add("active");
  });
  cards.forEach((card) => {
    const cardStatus = card.getAttribute("data-status") || "online";
    card.style.display = status === "all" || cardStatus === status ? "block" : "none";
  });
  const dropdown = document.querySelector(".filter-dropdown-menu");
  if (dropdown) dropdown.classList.remove("show");

  // Hide category 
  document.querySelectorAll(".category-group").forEach((group) => {
    const hasVisible = Array.from(group.querySelectorAll(".system-card"))
      .some(c => c.style.display !== "none");
    group.style.display = hasVisible ? "" : "none";
  });

  const visibleCards  = Array.from(cards).filter((card) => card.style.display !== "none");
  const cardsGrid     = document.querySelector(".cards-grid");
  const existingEmpty = cardsGrid?.querySelector(".filter-empty-state");
  if (existingEmpty) existingEmpty.remove();
  if (visibleCards.length === 0 && cardsGrid) {
    const emptyState = document.createElement("div");
    emptyState.className       = "empty-state filter-empty-state";
    emptyState.style.gridColumn = "1/-1";
    emptyState.innerHTML = `<svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg><h3>No Systems Found</h3><p>No systems match the selected filter: ${status}</p>`;
    cardsGrid.appendChild(emptyState);
  }
}

//  Dashboard Chart 
let dashboardChart = null;
document.addEventListener("DOMContentLoaded", function () {
  loadDashboardChart();
});

function loadDashboardChart() {
  fetch("../backend/get_analytics_data.php?action=systems_by_status")
    .then((response) => response.json())
    .then((data) => { if (data.success) renderDashboardChart(data.data); })
    .catch((error) => console.error("Error loading dashboard chart:", error));
}

function refreshDashboardWidgets() {
  loadDashboardChart();
  if (typeof loadCalendarSchedules === "function") loadCalendarSchedules();
}

function renderDashboardChart(data) {
  const ctx = document.getElementById("systemsStatusChart");
  if (!ctx) return;
  const labels = [], counts = [], colors = [];
  const statusColors = {
    online: "rgba(16,185,129,0.8)", maintenance: "rgba(245,158,11,0.8)",
    down: "rgba(239,68,68,0.8)", offline: "rgba(107,114,128,0.8)", archived: "rgba(156,163,175,0.8)",
  };
  data.forEach((item) => {
    labels.push(item.status.charAt(0).toUpperCase() + item.status.slice(1));
    counts.push(item.count);
    colors.push(statusColors[item.status] || "rgba(30,58,138,0.8)");
  });
  if (dashboardChart) dashboardChart.destroy();
  const maxValue     = Math.max(...counts);
  const suggestedMax = maxValue + Math.ceil(maxValue * 0.9);
  dashboardChart = new Chart(ctx, {
    type: "bar",
    data: { labels, datasets: [{ label: "Number of Systems", data: counts, backgroundColor: colors, borderColor: colors.map((c) => c.replace("0.8", "1")), borderWidth: 1 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 5 }, suggestedMax: suggestedMax < 2 ? 2 : suggestedMax } },
    },
  });
}

// Responsive Hamburger Menu 
function toggleSidebar() {
  document.body.classList.toggle("sidebar-open");
  const overlay = document.querySelector(".sidebar-overlay");
  if (overlay) overlay.classList.toggle("show");
}
function closeSidebar() {
  document.body.classList.remove("sidebar-open");
  const overlay = document.querySelector(".sidebar-overlay");
  if (overlay) overlay.classList.remove("show");
}
function handleNavLinkClick() {
  if (window.innerWidth <= 1024) closeSidebar();
}

function initializeResponsiveMenu() {
  if (!document.querySelector(".sidebar-overlay")) {
    const overlay = document.createElement("div");
    overlay.className = "sidebar-overlay";
    overlay.onclick = closeSidebar;
    document.body.appendChild(overlay);
  }
  if (!document.querySelector(".hamburger-menu")) {
    const hamburger = document.createElement("button");
    hamburger.className = "hamburger-menu";
    hamburger.setAttribute("aria-label", "Toggle navigation menu");
    hamburger.onclick = toggleSidebar;
    hamburger.innerHTML = `<span class="hamburger-line"></span><span class="hamburger-line"></span><span class="hamburger-line"></span>`;
    document.body.appendChild(hamburger);
  }
  document.querySelectorAll(".nav-menu a").forEach((link) => link.addEventListener("click", handleNavLinkClick));
  let resizeTimer;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { if (window.innerWidth > 1024) closeSidebar(); }, 250);
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && document.body.classList.contains("sidebar-open")) closeSidebar();
  });
}

document.addEventListener("DOMContentLoaded", function () { initializeResponsiveMenu(); });
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializeResponsiveMenu);
} else {
  initializeResponsiveMenu();
}

const STATUS_DOT_COLORS = {
  online: "#10B981", offline: "#6B7280", maintenance: "#F59E0B", down: "#EF4444", archived: "#9CA3AF",
};

function updateStatusDot(selectEl, dotId) {
  const dot = document.getElementById(dotId);
  if (!dot) return;
  dot.style.background = STATUS_DOT_COLORS[selectEl.value] || "#9CA3AF";
}

function updateCharCounter(inputEl, counterId, maxLen) {
  const counter = document.getElementById(counterId);
  if (!counter) return;
  const len = inputEl.value.length;
  counter.textContent = len + " / " + maxLen;
  counter.classList.remove("warn", "danger");
  if (len >= maxLen) counter.classList.add("danger");
  else if (len >= maxLen * 0.8) counter.classList.add("warn");
}

function handleLogoUpload(inputEl, previewBoxId, thumbId, filenameId, filesizeId) {
  const file = inputEl.files[0];
  const box  = document.getElementById(previewBoxId);
  if (!file || !box) return;
  const reader = new FileReader();
  reader.onload = function (e) {
    document.getElementById(thumbId).src            = e.target.result;
    document.getElementById(filenameId).textContent = file.name;
    document.getElementById(filesizeId).textContent = formatFileSize(file.size);
    box.classList.add("visible");
  };
  reader.readAsDataURL(file);
}

function removeLogo(inputId, previewBoxId) {
  const input = document.getElementById(inputId);
  const box   = document.getElementById(previewBoxId);
  if (input) input.value = "";
  if (box) box.classList.remove("visible");
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(1) + " MB";
}

function previewLogo(input, previewId) {
  const preview = document.getElementById(previewId);
  const file    = input.files[0];
  if (file && preview) {
    const reader = new FileReader();
    reader.onload = function (e) { preview.src = e.target.result; preview.style.display = "block"; };
    reader.readAsDataURL(file);
  }
}

function showModalError(modalId, message) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  let banner = modal.querySelector(".modal-error-banner");
  if (!banner) {
    banner = document.createElement("div");
    banner.className = "modal-error-banner";
    const modalBody = modal.querySelector(".modal-body");
    const target    = modalBody || modal.querySelector(".modal-content");
    if (target) target.insertBefore(banner, target.firstChild);
  }
  banner.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="12" y1="8" x2="12" y2="12"></line>
      <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </svg>
    <span>${message}</span>`;
  banner.style.display = "flex";
  banner.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function clearModalError(modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  const banner = modal.querySelector(".modal-error-banner");
  if (banner) banner.style.display = "none";
}

function updateMarkOnlineBanner(systemId) {
  const banner = document.getElementById("markOnlineBanner");
  if (!banner) return;
  const card       = document.querySelector('.system-card[data-system-id="' + systemId + '"]');
  const liveStatus = card ? card.dataset.status : "online";
  banner.style.display = liveStatus === "maintenance" ? "block" : "none";
}

function markSystemOnline() {
  const systemId = document.getElementById("maintenanceSystemId").value;
  if (!systemId) return;
  const btn = document.querySelector(".btn-mark-online");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner" style="width:13px;height:13px;border-width:2px;display:inline-block;margin-right:6px;vertical-align:middle;"></span> Setting Online...';
  }
  fetch("../backend/maintenance/get_maintenance.php?action=system&system_id=" + systemId)
    .then((r) => r.json())
    .then((data) => {
      const btnIcon        = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>';
      const activeSchedule = (data.data || []).find((s) => s.status === "In Progress" || s.status === "Scheduled");
      if (!activeSchedule) { _markSystemOnlineDirect(systemId, btn, btnIcon); return; }
      const formData = new FormData();
      formData.append("action", "update");
      formData.append("id", activeSchedule.id);
      formData.append("system_id", systemId);
      formData.append("title", activeSchedule.title);
      formData.append("description", activeSchedule.description || "");
      formData.append("start_datetime", activeSchedule.start_datetime);
      formData.append("end_datetime", activeSchedule.end_datetime);
      formData.append("status", "Done");
      formData.append("change_to_online", "yes");
      fetch("../backend/maintenance/save_maintenance.php", { method: "POST", body: formData })
        .then((r) => r.json())
        .then((result) => {
          if (result.success) { _onMarkOnlineSuccess(systemId); }
          else {
            if (btn) { btn.disabled = false; btn.innerHTML = btnIcon + " Mark as Online"; }
            if (typeof showToast === "function") showToast(result.message || "Failed to update schedule.", "error");
          }
        })
        .catch(() => {
          if (btn) { btn.disabled = false; btn.innerHTML = btnIcon + " Mark as Online"; }
          if (typeof showToast === "function") showToast("Network error. Please try again.", "error");
        });
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Mark as Online'; }
      if (typeof showToast === "function") showToast("Network error. Please try again.", "error");
    });
}

function _markSystemOnlineDirect(systemId, btn, btnIcon) {
  const card   = document.querySelector('.system-card[data-system-id="' + systemId + '"]');
  const name   = card ? card.querySelector(".card-title")?.textContent.trim() : "";
  const domain = card ? card.querySelector(".card-domain")?.textContent.trim() : "";
  const formData = new FormData();
  formData.append("id", systemId);
  formData.append("name", name);
  formData.append("domain", domain);
  formData.append("status", "online");
  formData.append("change_note", "Manually set to Online after maintenance.");
  fetch("../backend/edit_system.php", { method: "POST", body: formData })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) { _onMarkOnlineSuccess(systemId); }
      else {
        if (btn) { btn.disabled = false; btn.innerHTML = btnIcon + " Mark as Online"; }
        if (typeof showToast === "function") showToast(data.message || "Failed to update status.", "error");
      }
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.innerHTML = btnIcon + " Mark as Online"; }
      if (typeof showToast === "function") showToast("Network error. Please try again.", "error");
    });
}

function _onMarkOnlineSuccess(systemId) {
  const card = document.querySelector('.system-card[data-system-id="' + systemId + '"]');
  if (card) {
    card.dataset.status = "online";
    const badge = card.querySelector(".card-status-badge");
    if (badge) badge.outerHTML = '<div class="card-status-badge status-online"><span class="status-indicator"></span>Online</div>';
    const contactMsg = card.querySelector(".system-contact-message");
    if (contactMsg) contactMsg.remove();
  }
  const banner = document.getElementById("markOnlineBanner");
  if (banner) banner.style.display = "none";
  if (typeof showToast === "function") showToast("System is now Online!", "success");
  if (typeof loadCalendarSchedules === "function") loadCalendarSchedules();
  if (typeof MaintenanceApp !== "undefined" && MaintenanceApp.sidePanelDate) {
    if (typeof loadSidePanelSchedules === "function") loadSidePanelSchedules(MaintenanceApp.sidePanelDate);
  }
  if (typeof closeMaintenanceModal === "function") closeMaintenanceModal();
}

// Entire card clickable 
function handleCardClick(event, systemId) {
  if (event.target.closest('.card-menu') ||
      event.target.closest('.dropdown-menu') ||
      event.target.closest('.bulk-checkbox-overlay') ||
      event.target.closest('button') ||
      event.target.closest('a')) { return; }
  openDomain(systemId);
}

// Japanese Domain Toggle 
let japaneseDomainActive = false;

function toggleJapaneseDomain(isChecked) {
  japaneseDomainActive = isChecked;
  document.querySelectorAll('.system-card').forEach(card => {
    const domainEl = card.querySelector('.card-domain');
    if (!domainEl) return;
    if (!card.getAttribute('data-eng-domain')) {
      card.setAttribute('data-eng-domain', domainEl.textContent.trim());
    }
    const engDomain      = card.getAttribute('data-eng-domain');
    const japaneseDomain = card.getAttribute('data-japanese-domain') || '';
    domainEl.textContent = (japaneseDomainActive && japaneseDomain) ? japaneseDomain : engDomain;
  });
}