<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/dashboard.php">
            <span class="align-middle">VibeMarket Panel</span>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-header">
                Tổng quan
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/dashboard.php">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-header">
                Quản lý người dùng
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/users.php">
                    <i class="align-middle" data-feather="users"></i> <span class="align-middle">Người dùng</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'sellers.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/sellers.php">
                    <i class="align-middle" data-feather="shopping-bag"></i> <span class="align-middle">Cửa hàng</span>
                </a>
            </li>

            <li class="sidebar-header">
                Thương mại
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/products.php">
                    <i class="align-middle" data-feather="package"></i> <span class="align-middle">Sản phẩm</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/VIBE_MARKET_BACKEND/VibeMarket-BE/panel/orders.php">
                    <i class="align-middle" data-feather="shopping-cart"></i> <span class="align-middle">Đơn hàng</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/coupons">
                    <i class="align-middle" data-feather="tag"></i> <span class="align-middle">Mã giảm giá</span>
                </a>
            </li>

            <li class="sidebar-header">
                Cộng đồng
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'community.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/community">
                    <i class="align-middle" data-feather="users"></i> <span class="align-middle">Cộng đồng</span>
                </a>
            </li>

            <li class="sidebar-header">
                Môi trường
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'trees.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/trees">
                    <i class="align-middle" data-feather="align-left"></i> <span class="align-middle">Cây trồng</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'learning.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/learning">
                    <i class="align-middle" data-feather="book-open"></i> <span class="align-middle">Học liệu</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/leaderboard">
                    <i class="align-middle" data-feather="award"></i> <span class="align-middle">Bảng xếp hạng</span>
                </a>
            </li>

            <li class="sidebar-header">
                Hệ thống
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="/panel/reports">
                    <i class="align-middle" data-feather="bar-chart-2"></i> <span class="align-middle">Báo cáo</span>
                </a>
            </li>

            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <a class="sidebar-link" href="#">
                    <i class="align-middle" data-feather="settings"></i> <span class="align-middle">Cài đặt</span>
                </a>
            </li>
        </ul>
    </div>
</nav>