<nav class="navbar navbar-expand navbar-light navbar-bg">
    <a class="sidebar-toggle js-sidebar-toggle">
        <i class="hamburger align-self-center"></i>
    </a>

    <div class="navbar-collapse collapse">
        <ul class="navbar-nav navbar-align">
            <li class="nav-item dropdown">
                <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
                    <i class="align-middle" data-feather="settings"></i>
                </a>

                <a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
                    <?php 
                    $session_avatar = Session::get('avatar', null);
                    $uploads_base = $this->getUploadsBaseUrl();
                    
                    if (empty($session_avatar)) {
                        $avatar_url = $this->baseUrl('img/avatars/default.jpg');
                    } elseif (filter_var($session_avatar, FILTER_VALIDATE_URL)) {
                        $avatar_url = $session_avatar;
                    } else {
                        if (strpos($session_avatar, 'uploads/') !== false) {
                            $avatar_url = $uploads_base . ltrim($session_avatar, '/');
                        } else {
                            $avatar_url = $uploads_base . 'uploads/avatars/' . ltrim($session_avatar, '/');
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>"
                        class="avatar img-fluid rounded me-1" alt="<?php echo htmlspecialchars(Session::get('name', 'Admin')); ?>" 
                        onerror="this.src='<?php echo $this->baseUrl('img/avatars/default.jpg'); ?>'" />
                    <span class="text-dark"><?php echo htmlspecialchars(Session::get('name', 'Admin')); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="#">
                        <i class="align-middle me-1" data-feather="help-circle"></i> Supper Admin
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?php echo $this->baseUrl('auth/logout'); ?>">
                        <i class="align-middle me-1" data-feather="log-out"></i> Đăng xuất
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>
