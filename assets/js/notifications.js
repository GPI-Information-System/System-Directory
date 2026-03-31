/* NOTIFICATION BELL JAVASCRIPT */

let notificationCheckInterval = null;
let lastNotificationCount = 0;
let allNotifications = [];

const NOTIF_JP = {
  header: "最新情報",
  markAllRead: "全て既読にする",
  allCaughtUp: "全て確認済み！",
  noChanges: "過去24時間のステータス変更はありません",
  contact: "支援のために {number} にお問い合わせください",
  status: {
    online: "オンライン",
    offline: "オフライン",
    maintenance: "メンテナンス",
    down: "ダウン",
    archived: "アーカイブ済み",
  },
};

function isJpMode() {
  return localStorage.getItem("gportal_jp_mode") === "true";
}

function notifStatusLabel(status) {
  if (isJpMode()) return NOTIF_JP.status[status] || capitalize(status);
  return capitalize(status);
}

function getReadIds() {
  try {
    return new Set(
      JSON.parse(localStorage.getItem("gportal_read_notifs") || "[]"),
    );
  } catch {
    return new Set();
  }
}

function saveReadIds(set) {
  localStorage.setItem("gportal_read_notifs", JSON.stringify([...set]));
}

function markAsRead(id) {
  const ids = getReadIds();
  ids.add(id);
  saveReadIds(ids);
}

function markAllRead() {
  const ids = getReadIds();
  allNotifications.forEach((n) => ids.add(n.id));
  saveReadIds(ids);
  renderNotifications(allNotifications);
  updateNotificationBadge(0);
}

document.addEventListener("DOMContentLoaded", function () {
  initializeNotifications();
});

function initializeNotifications() {
  loadNotifications();
  notificationCheckInterval = setInterval(loadNotifications, 30000);
  document.addEventListener("click", function (event) {
    const bellContainer = document.querySelector(".notification-bell");
    const dropdown = document.getElementById("notificationDropdown");
    if (bellContainer && !bellContainer.contains(event.target)) {
      dropdown?.classList.remove("show");
    }
  });
}

function toggleNotifications() {
  const dropdown = document.getElementById("notificationDropdown");
  const isShowing = dropdown.classList.contains("show");
  if (isShowing) {
    dropdown.classList.remove("show");
  } else {
    dropdown.classList.add("show");
    loadNotifications();
  }
}

function loadNotifications() {
  fetch("../backend/get_notifications.php?hours=24")
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        allNotifications = data.notifications || [];
        const readIds = getReadIds();
        const unreadCount = allNotifications.filter(
          (n) => !readIds.has(n.id),
        ).length;
        updateNotificationBadge(unreadCount);
        renderNotifications(allNotifications);
        lastNotificationCount = unreadCount;
      }
    })
    .catch((err) => console.error("Error loading notifications:", err));
}

function updateNotificationBadge(count) {
  const badge = document.getElementById("notificationBadge");
  const countEl = document.getElementById("notificationCount");
  if (count > 0) {
    badge.style.display = "flex";
    badge.textContent = count > 9 ? "9+" : count;
  } else {
    badge.style.display = "none";
  }
  if (countEl) countEl.textContent = count;
}

function renderNotifications(notifications) {
  const container = document.getElementById("notificationList");
  const readIds = getReadIds();
  const jp = isJpMode();

  const headerEl = document.querySelector(".notification-header h3");
  if (headerEl) headerEl.textContent = jp ? NOTIF_JP.header : "Recent Updates";

  const markAllBtn = document.getElementById("markAllReadBtn");
  if (markAllBtn) {
    const hasUnread =
      notifications && notifications.some((n) => !readIds.has(n.id));
    markAllBtn.style.display = hasUnread ? "block" : "none";
    markAllBtn.textContent = jp ? NOTIF_JP.markAllRead : "Mark all read";
  }

  if (!notifications || notifications.length === 0) {
    container.innerHTML = `
            <div class="notification-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <p>${jp ? NOTIF_JP.allCaughtUp : "All caught up!"}</p>
                <span>${jp ? NOTIF_JP.noChanges : "No status changes in the last 24 hours"}</span>
            </div>
        `;
    return;
  }

  container.innerHTML = notifications
    .map((notif) => {
      const isUnread = !readIds.has(notif.id);
      const isActionable = ["down", "offline"].includes(notif.new_status);
      const exactTime = formatExactTime(notif.changed_at);
      const relTime = getTimeAgo(notif.changed_at);

      const contactText = jp
        ? NOTIF_JP.contact.replace(
            "{number}",
            `<strong>${escapeHtml(notif.contact_number)}</strong>`,
          )
        : `Contact <strong>${escapeHtml(notif.contact_number)}</strong> for assistance`;

      const contactHint =
        isActionable && notif.contact_number
          ? `<div class="notification-contact-hint">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    ${contactText}
               </div>`
          : "";

      return `
            <div class="notification-item ${isUnread ? "unread" : ""} ${isActionable ? "actionable" : ""}"
                 onclick="handleNotificationClick(${notif.system_id}, ${notif.id})">
                ${isUnread ? '<span class="unread-dot"></span>' : ""}
                <div class="notification-item-header">
                    <div class="notification-system-name">${escapeHtml(notif.system_name)}</div>
                    <div class="notification-time" title="${exactTime}">${relTime}</div>
                </div>
                <div class="notification-status-change">
                    <span class="notification-status-badge ${notif.old_status}">${notifStatusLabel(notif.old_status)}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                    <span class="notification-status-badge ${notif.new_status}">${notifStatusLabel(notif.new_status)}</span>
                </div>
                ${contactHint}
                <div class="notification-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    ${exactTime}
                </div>
            </div>
        `;
    })
    .join("");
}

function handleNotificationClick(systemId, notifId) {
  markAsRead(notifId);
  const readIds = getReadIds();
  const unreadCount = allNotifications.filter((n) => !readIds.has(n.id)).length;
  updateNotificationBadge(unreadCount);
  renderNotifications(allNotifications);
  document.getElementById("notificationDropdown").classList.remove("show");

  const systemCard = document.querySelector(`[data-system-id="${systemId}"]`);
  if (systemCard) {
    systemCard.scrollIntoView({ behavior: "smooth", block: "center" });
    systemCard.style.transition = "all 0.3s";
    systemCard.style.transform = "scale(1.02)";
    systemCard.style.boxShadow = "0 8px 24px rgba(30, 58, 138, 0.3)";
    setTimeout(() => {
      systemCard.style.transform = "";
      systemCard.style.boxShadow = "";
    }, 1200);
  }
}

function formatExactTime(datetime) {
  const d = new Date(datetime);
  return (
    d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" }) +
    " · " +
    d.toLocaleDateString("en-US", { month: "short", day: "numeric" })
  );
}

function getTimeAgo(datetime) {
  const now = new Date();
  const then = new Date(datetime);
  const seconds = Math.floor((now - then) / 1000);
  if (seconds < 60) return "Just now";
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return then.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

function capitalize(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

window.addEventListener("beforeunload", function () {
  if (notificationCheckInterval) clearInterval(notificationCheckInterval);
});
