<!doctype html>
<html lang="en">
<?php
// echo password_hash("Test@123", PASSWORD_DEFAULT)
?>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ADMIN</title>
  <link rel="shortcut icon" type="image/png" href="/admin/assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="/admin/assets/css/styles.css" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <div
      class="position-relative overflow-hidden radial-gradient min-vh-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-4 col-xxl-3">
            <div class="card mb-0">
              <div class="card-body">
                 <div class="d-flex justify-content-center">

                   <img src="/admin/assets/images/logos/logo.png" alt="" class="w-50 mb-4 text-center pb-2">
                 </div>
                 <form action="/admin/login.php" method="post">
                  <div class="mb-3">
                    <label for="exampleInputEmail1" class="form-label">Email</label>
                    <input name="email" required type="email" placeholder="email@gmail.com" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
                  </div>
                  <div class="mb-4">
                    <label for="exampleInputPassword1" class="form-label">Password</label>
                    <input name="password" placeholder="********" required type="password" class="form-control" id="exampleInputPassword1">
                  </div>
                  <button type="submit" id="signInBtn" class="btn btn-primary w-100 py-8 fs-4 mb-2 rounded-2">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loading" style="display: none;">
                      <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                      Signing In...
                    </span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    function showToast(message, type = "info") {
      const toast = document.createElement('div');
      toast.className = "custom-toast " + type;
      toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="close-btn">&times;</button>
      `;
      document.body.appendChild(toast);

      // Auto remove after 5 seconds
      setTimeout(() => {
        toast.style.animation = "fadeOut 0.5s ease";
        setTimeout(() => toast.remove(), 500);
      }, 5000);
    }

    // Read URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('err')) {
      showToast(urlParams.get('err'), "error");
    }
    if (urlParams.get('msg')) {
      showToast(urlParams.get('msg'), "success");
    }

    // Handle form submission with loading state
    document.querySelector('form').addEventListener('submit', function(e) {
      const signInBtn = document.getElementById('signInBtn');
      const btnText = signInBtn.querySelector('.btn-text');
      const btnLoading = signInBtn.querySelector('.btn-loading');
      
      // Show loading state
      signInBtn.disabled = true;
      btnText.style.display = 'none';
      btnLoading.style.display = 'inline-block';
    });

    // Styles
    const style = document.createElement("style");
    style.innerHTML = `
      .custom-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 18px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        color: #fff;
        font-weight: 600;
        font-family: Arial, sans-serif;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: fadeIn 0.4s ease;
        z-index: 9999;
      }
      .custom-toast.success {
        background: linear-gradient(135deg, #28a745, #218838);
      }
      .custom-toast.error {
        background: linear-gradient(135deg, #ff4d4d, #cc0000);
      }
      .custom-toast .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 16px;
        margin-left: 10px;
        cursor: pointer;
      }
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
      }
      @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(20px); }
      }
    `;
    document.head.appendChild(style);
  </script>

  <script src="/admin/assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="/admin/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>