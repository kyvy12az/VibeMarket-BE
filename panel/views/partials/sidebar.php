<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand" href="<?php echo $this->baseUrl('dashboard'); ?>">
            <span class="align-middle">VibeMarket Panel</span>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-header">
                Tổng quan
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && $page_title == 'Dashboard') ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('dashboard'); ?>">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-header">
                Quản lý người dùng
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && strpos($page_title, 'người dùng') !== false) ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('users'); ?>">
                    <i class="align-middle" data-feather="users"></i> <span class="align-middle">Người dùng</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && strpos($page_title, 'cửa hàng') !== false) ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('sellers'); ?>">
                    <i class="align-middle" data-feather="shopping-bag"></i> <span class="align-middle">Cửa hàng</span>
                </a>
            </li>

            <li class="sidebar-header">
                Thương mại
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && strpos($page_title, 'sản phẩm') !== false) ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('products'); ?>">
                    <i class="align-middle" data-feather="package"></i> <span class="align-middle">Sản phẩm</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && strpos($page_title, 'đơn hàng') !== false) ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('orders'); ?>">
                    <i class="align-middle" data-feather="shopping-cart"></i> <span class="align-middle">Đơn hàng</span>
                </a>
            </li>

            <li class="sidebar-header">
                Cộng đồng
            </li>

            <li class="sidebar-item <?php echo (isset($page_title) && strpos($page_title, 'bài viết') !== false) ? 'active' : ''; ?>">
                <a class="sidebar-link" href="<?php echo $this->baseUrl('posts'); ?>">
                    <i class="align-middle" data-feather="message-square"></i> <span class="align-middle">Bài viết</span>
                </a>
            </li>

            <li class="sidebar-header">
                Cài đặt
            </li>

            <li class="sidebar-item">
                <a class="sidebar-link" href="#">
                    <i class="align-middle" data-feather="settings"></i> <span class="align-middle">Cấu hình</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
