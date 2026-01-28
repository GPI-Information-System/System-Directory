// Login
$('#login').on('click', function () {
  let hasError = false;
  $('#user_email, #password').each(function () {
    const value = $(this).val().trim();
    if (!value) {
      $(this).addClass('border-danger');
      hasError = true;
    } else {
      $(this).removeClass('border-danger');
    }
  });

  $('.text-danger').each(function () {
    $(this).addClass('d-none');
  });

  if (!hasError) {
    $.ajax({
      type: "POST",
      url: "include/login.php",
      data: new FormData($('#loginDetails')[0]),
      processData: false,
      contentType: false,
      success: function (response) {
        if (response === 'Success') {
          window.location.href = 'pages/index.php';
        } else if (response === 'Invalid') {
          $('.text-danger').each(function () {
            $(this).removeClass('d-none');
            $(this).text('- Login or password is invalid.');
          });
        } else if (response === 'NA') {
          $('.text-danger').each(function () {
            $(this).removeClass('d-none');
            $(this).text('- Login provided is not registered.');
          });
        }
      },
      error: function (xhr, status, error) {
        console.log('Error occurred: ' + error);
      }
    });
  }
});
// End

// Show Tooltips
$(function () {
  $('[data-toggle="tooltip"]').tooltip();
});
// End

function itemDetails(id, readonly = false) {
  const data = { id: id, deviceInfo: true };
  if (readonly) {
    data.readonly = true;
  }
  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: data,
    success: function (response) {
      $("#viewModal .modal-content").html(response);
      $("#viewModal").modal("show");
      $(".selectpicker").selectpicker("refresh");

      $("#viewModal img").on("error", function () {
        this.src = "../uploads/broken_image.jpg";
      });
    },
  });
}

function deviceInfoSave(id) {
  const itemInfo = new FormData(document.getElementById("itemDetailsForm"));
  const itemImage = document.getElementById("itemImageInput").files[0];
  const removePhoto = document.getElementById("removePhoto").value;
  const noUserCheck = document.getElementById("noUserCheck"); // Get the checkbox

  // Determine if checkbox is checked (1 if true, 0 if false)
  const isCheck = noUserCheck.checked ? 1 : 0;

  // Append values to FormData
  itemInfo.append("removePhoto", removePhoto);
  itemInfo.append("itemImage", itemImage);
  itemInfo.append("id", id);
  itemInfo.append("deviceInfoSave", true);
  itemInfo.append("isCheck", isCheck); // Add checkbox state
  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: itemInfo,
    processData: false,
    contentType: false,
    success: function (response) {
      if (response == "Success") {
        alert("Device information saved successfully");
        location.reload();
      } else {
        alert(response);
      }
    },
  });
}

function addItem() {
  $("#viewModal").modal("show");

  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: { addItem: true },
    success: function (response) {
      $("#viewModal .modal-content").html(response);
      $("#viewModal").modal("show");
      $(".selectpicker").selectpicker("refresh");
    },
  });
}

function deviceSave() {
  const itemInfo = new FormData(document.getElementById("additemForm"));
  itemInfo.append("saveItem", true);
  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: itemInfo,
    processData: false,
    contentType: false,
    success: function (response) {
      if (response == "Success") {
        alert("Item added successfully");
        location.reload();
      } else {
        alert(response);
      }
    },
  });
}

function editSave() {
  const accountInfo = new FormData(document.getElementById("editForm"));
  accountInfo.append("editSave", true);
  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: accountInfo,
    processData: false,
    contentType: false,
    success: function (response) {
      if (response == "Success") {
        alert("Account information saved successfully");
        location.reload();
      } else {
        alert(response);
      }
    },
  });
}

$("#excelForm").on("submit", function (e) {
  e.preventDefault();

  const selectedAssets = $("#assetType").val();
  const selectedLocations = $("#location").val();

  $.ajax({
    url: "../include/generate-excel.php",
    method: "POST",
    data: {
      assetType: selectedAssets.join(","), // Send as comma-separated string
      location: selectedLocations.join(","),
    },
    xhrFields: {
      responseType: "blob", // Important: treat response as binary
    },
    success: function (data, status, xhr) {
      const blob = new Blob([data], { type: "application/vnd.ms-excel" });
      const downloadUrl = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = downloadUrl;
      a.download = "AMS-Assets-Location.xls";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    },
    error: function () {
      alert("Failed to generate Excel file.");
    },
  });
});

function editInfo(id) {
  // $("#editItemDetails").modal("show");
  $.ajax({
    method: "GET",
    url: "../include/query.php?editInfo=true",
    data: { id: id },
    success: function (response) {
      $("#editItemDetails .modal-body").html(response);
      $("#editItemDetails").modal("show");
      $(".selectpicker").selectpicker("refresh");
    },
  });
}

function AssetEditSave() {
  const editInfo = new FormData(document.getElementById("additemForm"));
  editInfo.append("AssetEditSave", true);
  $.ajax({
    method: "POST",
    url: "../include/query.php",
    data: editInfo,
    processData: false,
    contentType: false,
    success: function (response) {
      if (response == "Success") {
        alert("Item information updated successfully");
        location.reload();
      } else {
        alert(response);
      }
    },
  });
}

// Change Password
function changePassword() {
  const changePassForm = new FormData(
    document.getElementById("changePassForm")
  );
  $.ajax({
    method: "POST",
    url: "../include/query.php?changePassword=true",
    data: changePassForm,
    processData: false,
    contentType: false,
    success: function (response) {
      if (response == "Success") {
        alert("Password changed successfully");
        window.location.href = "../include/logout.php";
      } else {
        alert(response);
      }
    },
  });
}
// End

// Set Sidebar Active
document.addEventListener("DOMContentLoaded", function () {
  let currentUrl = window.location.pathname + window.location.search;
  let navItems = document.querySelectorAll(".nav-item");

  // Remove existing active classes before applying new ones
  document.querySelectorAll(".nav-item.active, .nav-link:not(.collapsed), .collapse.show, .collapse-item.active").forEach(el => {
    el.classList.remove("active", "show", "collapsed");
  });

  navItems.forEach((navItem) => {
    let navLink = navItem.querySelector(".nav-link");
    let subMenu = navItem.querySelector(".collapse");
    let isActive = false;

    // Check if the main nav link has no collapse group and matches the URL
    if (!subMenu && navLink && navLink.href.includes(currentUrl)) {
      navItem.classList.add("active");
    }

    let subLinks = navItem.querySelectorAll(".collapse-item");
    subLinks.forEach((subLink) => {
      if (subLink.href.includes(currentUrl)) {
        isActive = true;
        subLink.classList.add("active"); // Add active to the clicked item
        if (subMenu) {
          subMenu.classList.add("show");
        }
        let parentCollapse = subMenu ? subMenu.closest(".collapse") : null;
        while (parentCollapse) {
          parentCollapse.classList.add("show");
          let parentNavItem = parentCollapse.closest(".nav-item");
          if (parentNavItem) {
            parentNavItem.classList.add("active");
            let parentNavLink = parentNavItem.querySelector(".nav-link");
            if (parentNavLink) {
              parentNavLink.classList.remove("collapsed");
            }
          }
          parentCollapse = parentNavItem ? parentNavItem.closest(".collapse") : null;
        }
      }
    });

    if (isActive) {
      navItem.classList.add("active");
      if (navLink) {
        navLink.classList.remove("collapsed");
      }
    }
  });
});
// End

// Dispose Asset
$('#disposalRequest').on('click', function () {
  let disposalRequestForm = new FormData($('#diposalForm')[0]);
  $.ajax({
    method: "POST",
    url: "../pages/ajax/request.php?id=Disposal",
    data: disposalRequestForm,
    contentType: false,
    processData: false,
    success: function (response) {
      if (response === "Success") {
        $('#successModal').modal('show');
      } else {
        $('#errorModal').modal('show');
        console.log(response);
      }
    },
    error: function (xhr, status, error) {
      console.error('❌ Error:', xhr, status, error);
      alert("An error occurred while adding the device.");
    }
  });
});
// End