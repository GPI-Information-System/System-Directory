/* G-Portal — Categories Management*/

const CategoriesApp = {
  categories: [],
  dragSrcIndex: null,
  pendingReorder: false,
  isSuperAdmin: false,
};

function openCategoriesModal() {
  const modal = document.getElementById("categoriesModal");
  if (!modal) return;

  CategoriesApp.isSuperAdmin = modal.dataset.superAdmin === "1";

  const addSection = document.getElementById("catAddSection");
  if (addSection)
    addSection.style.display = CategoriesApp.isSuperAdmin ? "block" : "none";

  modal.classList.add("show");
  loadCategories();
}

function closeCategoriesModal() {
  const modal = document.getElementById("categoriesModal");
  if (modal) modal.classList.remove("show");
  CategoriesApp.pendingReorder = false;
}

function loadCategories() {
  const listEl = document.getElementById("catList");
  if (!listEl) return;
  listEl.innerHTML =
    '<div class="cat-loading"><div class="loading-spinner" style="width:20px;height:20px;border-width:2px;"></div><span>Loading...</span></div>';

  fetch("../backend/categories/get_categories.php")
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) {
        listEl.innerHTML =
          '<div class="cat-empty">Failed to load categories.</div>';
        return;
      }
      CategoriesApp.categories = data.data || [];
      renderCategoryList();
    })
    .catch(() => {
      listEl.innerHTML =
        '<div class="cat-empty">Network error. Please try again.</div>';
    });
}

function renderCategoryList() {
  const listEl = document.getElementById("catList");
  if (!listEl) return;

  if (CategoriesApp.categories.length === 0) {
    listEl.innerHTML = '<div class="cat-empty">No categories found.</div>';
    return;
  }

  listEl.innerHTML = CategoriesApp.categories
    .map(
      (cat, index) => `
        <div class="cat-row"
             draggable="true"
             data-index="${index}"
             data-id="${cat.id}">

            <div class="cat-drag-handle" title="Drag to reorder">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="8"  x2="21" y2="8"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="16" x2="21" y2="16"></line>
                </svg>
            </div>

            <div class="cat-order-badge">${index + 1}</div>

            <div class="cat-name-wrap" id="cat-name-wrap-${cat.id}">
                <span class="cat-name" id="cat-name-display-${cat.id}">${escCat(cat.name)}</span>
                <input type="text"
                       class="cat-name-input"
                       id="cat-name-input-${cat.id}"
                       value="${escCat(cat.name)}"
                       maxlength="100"
                       style="display:none;"
                       onkeydown="onCatRenameKey(event, ${cat.id})"
                       onblur="cancelCatRename(${cat.id})">
            </div>

            <div class="cat-system-count" title="${cat.system_count} system(s) in this category">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                ${cat.system_count}
            </div>

            <div class="cat-actions">
                <button class="cat-btn-rename"
                        id="cat-btn-rename-${cat.id}"
                        onclick="startCatRename(${cat.id})"
                        title="Rename">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <!-- FIX: onmousedown="event.preventDefault()" prevents the input's onblur
                     from firing before onclick, which was causing the save button to
                     trigger cancelCatRename() first and reset the value. -->
                <button class="cat-btn-save"
                        id="cat-btn-save-${cat.id}"
                        onmousedown="event.preventDefault()"
                        onclick="saveCatRename(${cat.id})"
                        style="display:none;"
                        title="Save rename">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
                <button class="cat-btn-cancel-rename"
                        id="cat-btn-cancel-${cat.id}"
                        onmousedown="event.preventDefault()"
                        onclick="cancelCatRename(${cat.id})"
                        style="display:none;"
                        title="Cancel">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                ${
                  CategoriesApp.isSuperAdmin
                    ? `
                <button class="cat-btn-delete"
                        onmousedown="event.preventDefault()"
                        onclick="promptDeleteCategory(${cat.id}, '${escCat(cat.name)}', ${cat.system_count})"
                        title="Delete category">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>`
                    : ""
                }
            </div>
        </div>
    `,
    )
    .join("");

  // Attach drag events via JS
  listEl.querySelectorAll(".cat-row").forEach((row) => {
    row.addEventListener("dragstart", (e) =>
      onCatDragStart(e, parseInt(row.dataset.index)),
    );
    row.addEventListener("dragover", (e) => onCatDragOver(e));
    row.addEventListener("drop", (e) =>
      onCatDrop(e, parseInt(row.dataset.index)),
    );
    row.addEventListener("dragend", (e) => onCatDragEnd(e));
  });
}

function onCatDragStart(event, index) {
  CategoriesApp.dragSrcIndex = index;
  event.dataTransfer.effectAllowed = "move";
  event.dataTransfer.setData("text/plain", index);
  const row = event.currentTarget;
  setTimeout(() => row.classList.add("cat-dragging"), 0);
}

function onCatDragOver(event) {
  event.preventDefault();
  event.dataTransfer.dropEffect = "move";
  const row = event.currentTarget;
  document
    .querySelectorAll(".cat-row")
    .forEach((r) => r.classList.remove("cat-drag-over"));
  row.classList.add("cat-drag-over");
}

function onCatDrop(event, targetIndex) {
  event.preventDefault();
  document
    .querySelectorAll(".cat-row")
    .forEach((r) => r.classList.remove("cat-drag-over"));

  const srcIndex = CategoriesApp.dragSrcIndex;
  if (srcIndex === null || srcIndex === targetIndex) return;

  const moved = CategoriesApp.categories.splice(srcIndex, 1)[0];
  CategoriesApp.categories.splice(targetIndex, 0, moved);

  CategoriesApp.pendingReorder = true;
  renderCategoryList();
  saveReorder();
}

function onCatDragEnd(event) {
  CategoriesApp.dragSrcIndex = null;
  document.querySelectorAll(".cat-row").forEach((r) => {
    r.classList.remove("cat-dragging");
    r.classList.remove("cat-drag-over");
  });
}

function saveReorder() {
  const ids = CategoriesApp.categories.map((c) => c.id);
  const formData = new FormData();
  ids.forEach((id) => formData.append("ids[]", id));

  fetch("../backend/categories/reorder_categories.php", {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        CategoriesApp.pendingReorder = false;
        showCatToast("Category order saved", "success");
        refreshCategoryDropdowns();
      } else {
        showCatToast(data.message || "Failed to save order", "error");
      }
    })
    .catch(() => showCatToast("Network error saving order", "error"));
}

function startCatRename(catId) {
  const display = document.getElementById(`cat-name-display-${catId}`);
  const input = document.getElementById(`cat-name-input-${catId}`);
  const btnRename = document.getElementById(`cat-btn-rename-${catId}`);
  const btnSave = document.getElementById(`cat-btn-save-${catId}`);
  const btnCancel = document.getElementById(`cat-btn-cancel-${catId}`);
  if (!display || !input) return;

  display.style.display = "none";
  input.style.display = "block";
  btnRename.style.display = "none";
  btnSave.style.display = "inline-flex";
  btnCancel.style.display = "inline-flex";
  input.focus();
  input.select();
}

function cancelCatRename(catId) {
  const cat = CategoriesApp.categories.find((c) => c.id === catId);
  if (!cat) return;
  const display = document.getElementById(`cat-name-display-${catId}`);
  const input = document.getElementById(`cat-name-input-${catId}`);
  const btnRename = document.getElementById(`cat-btn-rename-${catId}`);
  const btnSave = document.getElementById(`cat-btn-save-${catId}`);
  const btnCancel = document.getElementById(`cat-btn-cancel-${catId}`);
  if (!display || !input) return;

  input.value = cat.name;
  display.style.display = "inline";
  input.style.display = "none";
  btnRename.style.display = "inline-flex";
  btnSave.style.display = "none";
  btnCancel.style.display = "none";
}

function onCatRenameKey(event, catId) {
  if (event.key === "Enter") {
    event.preventDefault();
    saveCatRename(catId);
  }
  if (event.key === "Escape") {
    cancelCatRename(catId);
  }
}

function saveCatRename(catId) {
  const input = document.getElementById(`cat-name-input-${catId}`);
  if (!input) return;
  const newName = input.value.trim();
  if (!newName) {
    showCatToast("Category name cannot be empty", "error");
    return;
  }

  const cat = CategoriesApp.categories.find((c) => c.id === catId);
  if (cat && cat.name === newName) {
    cancelCatRename(catId);
    return;
  }

  const btnSave = document.getElementById(`cat-btn-save-${catId}`);
  if (btnSave) {
    btnSave.disabled = true;
    btnSave.innerHTML = '<span style="opacity:.6">…</span>';
  }

  const formData = new FormData();
  formData.append("action", "rename");
  formData.append("id", catId);
  formData.append("name", newName);

  fetch("../backend/categories/save_category.php", {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        if (cat) cat.name = newName;
        showCatToast(data.message, "success");
        loadCategories();
        refreshCategoryDropdowns();
      } else {
        showCatToast(data.message || "Failed to rename", "error");
        cancelCatRename(catId);
      }
    })
    .catch(() => {
      showCatToast("Network error", "error");
      cancelCatRename(catId);
    });
}

function addNewCategory() {
  const input = document.getElementById("catNewNameInput");
  if (!input) return;
  const name = input.value.trim();
  if (!name) {
    showCatToast("Please enter a category name", "error");
    input.focus();
    return;
  }

  const btn = document.getElementById("catAddBtn");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Adding…";
  }

  const formData = new FormData();
  formData.append("action", "add");
  formData.append("name", name);

  fetch("../backend/categories/save_category.php", {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Add";
      }
      if (data.success) {
        input.value = "";
        showCatToast(data.message, "success");
        loadCategories();
        refreshCategoryDropdowns();
      } else {
        showCatToast(data.message || "Failed to add category", "error");
        input.focus();
      }
    })
    .catch(() => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Add";
      }
      showCatToast("Network error", "error");
    });
}

function onCatAddKeydown(event) {
  if (event.key === "Enter") {
    event.preventDefault();
    addNewCategory();
  }
}

function promptDeleteCategory(catId, catName, systemCount) {
  const modal = document.getElementById("catDeleteModal");
  if (!modal) return;

  document.getElementById("catDeleteName").textContent = catName;

  const reassignSection = document.getElementById("catReassignSection");
  const reassignSelect = document.getElementById("catReassignSelect");

  reassignSelect.innerHTML = CategoriesApp.categories
    .filter((c) => c.id !== catId)
    .map((c) => `<option value="${escCat(c.name)}">${escCat(c.name)}</option>`)
    .join("");

  if (systemCount > 0) {
    document.getElementById("catDeleteSystemNote").textContent =
      `This category contains ${systemCount} system(s). They will be moved to:`;
    reassignSection.style.display = "block";
  } else {
    document.getElementById("catDeleteSystemNote").textContent =
      "This category has no systems. It will be permanently deleted.";
    reassignSection.style.display = "none";
  }

  modal.dataset.deletingId = catId;
  modal.dataset.deletingName = catName;
  modal.dataset.systemCount = systemCount;
  modal.classList.add("show");
}

function closeCatDeleteModal() {
  const modal = document.getElementById("catDeleteModal");
  if (modal) modal.classList.remove("show");
}

function confirmDeleteCategory() {
  const modal = document.getElementById("catDeleteModal");
  if (!modal) return;

  const catId = parseInt(modal.dataset.deletingId);
  const sysCount = parseInt(modal.dataset.systemCount);
  const reassignTo =
    sysCount > 0 ? document.getElementById("catReassignSelect").value : "";

  const btn = document.getElementById("catDeleteConfirmBtn");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Deleting…";
  }

  const formData = new FormData();
  formData.append("id", catId);
  formData.append("reassign_to", reassignTo);

  fetch("../backend/categories/delete_category.php", {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((data) => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Delete";
      }
      if (data.success) {
        closeCatDeleteModal();
        showCatToast(data.message, "success");
        loadCategories();
        refreshCategoryDropdowns();
        setTimeout(() => location.reload(), 1200);
      } else {
        showCatToast(data.message || "Failed to delete", "error");
      }
    })
    .catch(() => {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Delete";
      }
      showCatToast("Network error", "error");
    });
}

function refreshCategoryDropdowns() {
  fetch("../backend/categories/get_categories.php")
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) return;
      const cats = data.data || [];

      const addSel = document.getElementById("systemCategory");
      if (addSel) {
        const current = addSel.value;
        addSel.innerHTML =
          '<option value="">Select a category...</option>' +
          cats
            .map(
              (c) =>
                `<option value="${escCat(c.name)}"${c.name === current ? " selected" : ""}>${escCat(c.name)}</option>`,
            )
            .join("");
      }

      const editSel = document.getElementById("editSystemCategory");
      if (editSel) {
        const current = editSel.value;
        editSel.innerHTML =
          '<option value="">Select a category...</option>' +
          cats
            .map(
              (c) =>
                `<option value="${escCat(c.name)}"${c.name === current ? " selected" : ""}>${escCat(c.name)}</option>`,
            )
            .join("");
      }

      const filterMenu = document.getElementById("categoryFilterMenu");
      if (filterMenu) {
        filterMenu.innerHTML =
          `<button type="button" onclick="filterByCategory('all')" class="filter-option active" data-cat="all">
                        <span class="filter-dot" style="background:var(--primary-light);"></span>All Categories
                    </button>` +
          cats
            .map(
              (c) => `
                        <button type="button" onclick="filterByCategory('${escCat(c.name)}')" class="filter-option" data-cat="${escCat(c.name)}">
                            <span class="filter-dot" style="background:var(--primary-color);"></span>${escCat(c.name)}
                        </button>`,
            )
            .join("");
      }
    })
    .catch(() => {});
}

function showCatToast(message, type) {
  if (typeof showToast === "function") {
    showToast(message, type);
    return;
  }
  const el = document.createElement("div");
  el.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;
        border-radius:8px;color:#fff;font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,.2);
        background:${type === "success" ? "#10b981" : "#ef4444"}`;
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

function escCat(str) {
  if (!str) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Close modals on outside click
document.addEventListener("click", function (e) {
  const modal = document.getElementById("categoriesModal");
  const delModal = document.getElementById("catDeleteModal");
  if (modal && e.target === modal) closeCategoriesModal();
  if (delModal && e.target === delModal) closeCatDeleteModal();
});

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeCategoriesModal();
    closeCatDeleteModal();
  }
});
