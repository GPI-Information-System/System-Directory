<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Cache control meta tags -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

  <title>Asset Management System</title>

  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/logo.png">

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="assets/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

</head>

<body class="bg-image">
  <section class="vh-100">
    <div class="container py-5 h-100">
      <div class="row d-flex justify-content-center align-items-center h-100">
        <div class="col col-5">
          <div class="card shadow" style="border-radius: 1rem; background-color: rgba(255, 255, 255, 0.8); color: #000000">
            <div class="row g-0">
              <div class="col-12 d-flex align-items-center">
                <div class="card-body p-4 p-lg-5 text-black">

                  <form id="loginDetails">

                    <div class="d-flex flex-column align-items-center justify-content-center mb-5">
                      <img src="assets/img/Logo.png" style="width: 80px;" class="mb-2">
                      <span class="h5 font-weight-bold mb-0">Asset Mangement System</span>
                    </div>

                    <h5 class="fw-normal mb-3 pb-3" style="letter-spacing: 1px;">Sign into your account</h5>

                    <div class="form-outline mb-4">
                      <input type="username" id="username" name="username" class="form-control form-control-lg" />
                      <label class="form-label" for="username">Username</label> <i class="small text-danger d-none">- Login or password is invalid.</i>
                    </div>

                    <div class="form-outline mb-4">
                      <input type="password" id="password" name="password" class="form-control form-control-lg" />
                      <label class="form-label" for="password">Password</label> <i class="small text-danger d-none">- Login or password is invalid.</i>
                    </div>

                  </form>

                  <div class="pt-1 mb-4">
                    <button class="btn-block bounce" id="login">Login</button>
                  </div>

                  <div class="form-outline mb-4 d-flex justify-content-between align-items-center">
                    <!-- <a class="btn btn-sm text-primary">Create Account</a> -->
                    <!-- <a class="btn btn-sm text-muted">Forgot password?</a> -->
                  </div>

                  <div class="text-center">
                    <small>Version 1.0.0</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Bootstrap core JavaScript-->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript-->
  <script src="assets/js/jquery.easing.min.js"></script>

  <!-- Custom scripts for all pages-->
  <script src="assets/js/sb-admin-2.min.js"></script>

  <script>
    $(document).on('keypress', function(event) {
      if (event.which === 13) {
        $('#login').click();
      }
    });
  </script>

  <script src="assets/js/script.js"></script>
</body>

</html>