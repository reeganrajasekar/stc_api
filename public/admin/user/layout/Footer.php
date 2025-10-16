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


</body>

</html>
<?php $conn->close(); ?>
