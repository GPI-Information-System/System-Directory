/* G-Portal — Real-Time Health Check + Maintenance Check */

(function GPortalHealthCheck() {

  const POLL_INTERVAL = 30000;

  const STATUS_LABELS = {
    online: "Online",
    offline: "Offline",
    maintenance: "Maintenance",
    down: "Down",
    archived: "Archived",
  };

  const IS_VIEWER = document.body.classList.contains("viewer-page");
  const CARD_CLASS = IS_VIEWER ? ".system-card-viewer" : ".system-card";
  const BADGE_CLASS = IS_VIEWER ? ".status-badge-viewer" : ".card-status-badge";

  // Endpoints
  const HEALTH_ENDPOINT = IS_VIEWER
    ? "../backend/trigger_health_check.php?source=viewer"
    : "../backend/trigger_health_check.php";

  const MAINTENANCE_ENDPOINT = IS_VIEWER
    ? "../backend/maintenance/trigger_maintenance_check.php?source=viewer"
    : "../backend/maintenance/trigger_maintenance_check.php";

  const STATUS_ENDPOINT = IS_VIEWER
    ? "../backend/get_systems_status.php?source=viewer"
    : "../backend/get_systems_status.php";

  let _reloadPending = false;

  function reloadDashboard() {
    if (_reloadPending) return;
    _reloadPending = true;
    location.reload();
  }

  function buildBadgeHTML(status) {
    var label = STATUS_LABELS[status] || status;
    var badgeClass = IS_VIEWER ? "status-badge-viewer" : "card-status-badge";
    var dotClass = IS_VIEWER ? "status-indicator-viewer" : "status-indicator";

    return (
      '<div class="' +
      badgeClass +
      " status-" +
      status +
      '">' +
      '<span class="' +
      dotClass +
      '"></span>' +
      label +
      "</div>"
    );
  }

  function updateCard(systemId, newStatus) {
    var card = document.querySelector(
      CARD_CLASS + '[data-system-id="' + systemId + '"]',
    );
    if (!card) return;

    var oldStatus = card.dataset.status;
    var showContact = ["maintenance", "offline", "down"].includes(newStatus);

    card.dataset.status = newStatus;

    var oldBadge = card.querySelector(BADGE_CLASS);
    if (oldBadge) {
      oldBadge.outerHTML = buildBadgeHTML(newStatus);
    }

    var contactMsg = card.querySelector(".system-contact-message");
    if (showContact && !contactMsg) {
      var contactNumber = card.dataset.contactNumber || "123";
      card.insertAdjacentHTML("beforeend", buildContactHTML(contactNumber));
    } else if (!showContact && contactMsg) {
      contactMsg.remove();
    }

    card.classList.add("status-changed-flash");
    setTimeout(function () {
      card.classList.remove("status-changed-flash");
    }, 1500);

    console.log(
      "[G-Portal] Card #" +
        systemId +
        " updated: " +
        oldStatus +
        " -> " +
        newStatus,
    );
  }

  function buildContactHTML(contactNumber) {
    return (
      '<div class="system-contact-message">' +
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>' +
      "</svg>" +
      'Contact <span class="contact-number">' +
      contactNumber +
      "</span> for assistance" +
      "</div>"
    );
  }

  function showToast(message, type) {
    var toast = document.getElementById("gportal-hc-toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "gportal-hc-toast";
      toast.style.cssText =
        "position:fixed;bottom:24px;right:24px;z-index:9999;" +
        "color:#fff;padding:10px 18px;border-radius:8px;" +
        "font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,0.25);" +
        "transition:opacity 0.3s ease;opacity:0;pointer-events:none;";
      document.body.appendChild(toast);
    }
    toast.style.background = type === "maintenance" ? "#7c3aed" : "#b45309";
    toast.textContent = message;
    toast.style.opacity = "1";
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(function () {
      toast.style.opacity = "0";
    }, 3000);
  }

  // Health check AJAX poll
  function runHealthCheck() {
    $.ajax({
      url: HEALTH_ENDPOINT,
      method: "GET",
      dataType: "json",
      success: function (data) {
        if (!data.success) {
          console.warn("[G-Portal] Health check error:", data.message);
          return;
        }
        if (data.changed > 0) {
          if (IS_VIEWER) {
            reloadDashboard();
            return;
          }
          reloadDashboard();
        }
      },
      error: function (err) {
        console.warn("[G-Portal] Health check AJAX failed:", err);
      },
    });
  }

  // Maintenance check AJAX poll
  function runMaintenanceCheck() {
    $.ajax({
      url: MAINTENANCE_ENDPOINT,
      method: "GET",
      dataType: "json",
      success: function (data) {
        if (!data.success) {
          console.warn("[G-Portal] Maintenance check error:", data.message);
          return;
        }
        if (data.switched > 0) {
          reloadDashboard();
        }
      },
      error: function (err) {
        console.warn("[G-Portal] Maintenance check AJAX failed:", err);
      },
    });
  }


  function runViewerStatusPoll() {
    if (!IS_VIEWER) return;

    $.ajax({
      url: STATUS_ENDPOINT,
      method: "GET",
      dataType: "json",
      success: function (data) {
        if (!data.success || !data.systems) return;

        var hasChange = false;

        data.systems.forEach(function (sys) {
          var card = document.querySelector(
            '.system-card-viewer[data-system-id="' + sys.id + '"]',
          );
          if (card && card.dataset.status !== sys.status) {
            console.log(
              "[G-Portal Viewer] Status mismatch for #" +
                sys.id +
                ": page=" +
                card.dataset.status +
                " db=" +
                sys.status,
            );
            hasChange = true;
          }
        });

        if (hasChange) {
          console.log(
            "[G-Portal Viewer] Status change detected — reloading page",
          );
          reloadDashboard();
        }
      },
      error: function () {
        
      },
    });
  }

 
  $(document).ready(function () {
    runHealthCheck();
    runMaintenanceCheck();

    if (IS_VIEWER) runViewerStatusPoll();

    setInterval(function () {
      runHealthCheck();
      runMaintenanceCheck();
      if (IS_VIEWER) runViewerStatusPoll();
    }, POLL_INTERVAL);
  });
})();