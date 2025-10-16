<header class="app-header bg-light mt-1">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <ul class="navbar-nav">
            <li class="nav-item d-block d-xl-none">
                <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)">
                    <i class="ti ti-menu-2"></i>
                </a>
            </li>
        </ul>
        <div class="navbar-collapse justify-content-end px-0 bg-light" id="navbarNav">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
                <li>
                    <span class="d-flex flex-column gap-1 align-items-end">
                        <span class="h5 p-0 m-0 text-primary"><?php echo $_SESSION['user'] ?></span>
                        <span class="h6 p-0 m-0 text-muted"><?php echo $_SESSION['role'] ?></span>
                    </span>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="/admin/assets/images/profile/user-1.jpg" alt="" width="35" height="35" class="rounded-circle">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                        <?php
                        if ($_SESSION['role'] == "SuperAdmin") {
                        ?>
                            <!-- <div class="message-body">
                            <a href="/user/cp.php" class="btn btn-outline-primary mx-3 mt-2 d-block">Control Panel</a>
                        </div> -->
                        <?php
                        }
                        ?>
                        <div class="message-body">
                            <a href="/admin/logout.php" class="btn btn-outline-primary mx-3 mt-2 d-block">Logout</a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</header>