<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Session.php';

class DashboardController extends Controller
{
    private $dashboardModel;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardModel = $this->model('DashboardModel');
    }

    public function index()
    {
        $this->requireAuth();

        $stats = $this->dashboardModel->getStats();
        $revenue_chart = $this->dashboardModel->getRevenueChart(12);
        $recent_orders = $this->dashboardModel->getRecentOrders(5);
        $active_users = $this->model('UserModel')->findAll(5);

        $this->view('dashboard/index', [
            'stats' => $stats,
            'revenue_chart' => $revenue_chart,
            'recent_orders' => $recent_orders,
            'active_users' => $active_users,
            'page_title' => 'Dashboard',
            'uploads_base' => $this->getUploadsBaseUrl()
        ]);
    }
}
