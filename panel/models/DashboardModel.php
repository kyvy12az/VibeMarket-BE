<?php

require_once __DIR__ . '/../core/Model.php';

class DashboardModel extends Model
{
    public function getStats()
    {
        $stats = [];

        // Total users
        $result = $this->fetchOne("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = $result ? $result['total'] : 0;

        // Total products
        $result = $this->fetchOne("SELECT COUNT(*) as total FROM products");
        $stats['total_products'] = $result ? $result['total'] : 0;

        // Total orders
        $result = $this->fetchOne("SELECT COUNT(*) as total FROM orders");
        $stats['total_orders'] = $result ? $result['total'] : 0;

        // Monthly revenue
        $result = $this->fetchOne(
            "SELECT SUM(total) as revenue FROM orders 
             WHERE status = 'delivered' 
             AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
        $stats['monthly_revenue'] = $result ? ($result['revenue'] ?? 0) : 0;

        // Today's orders
        $result = $this->fetchOne(
            "SELECT COUNT(*) as today_orders FROM orders 
             WHERE DATE(created_at) = CURDATE()"
        );
        $stats['today_orders'] = $result ? $result['today_orders'] : 0;

        return $stats;
    }

    public function getRevenueChart($months = 12)
    {
        $revenue_chart = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $result = $this->fetchOne(
                "SELECT SUM(total) as revenue FROM orders 
                 WHERE status = 'delivered' 
                 AND DATE_FORMAT(created_at, '%Y-%m') = ?",
                [$month]
            );
            
            $revenue = $result ? ($result['revenue'] ?? 0) : 0;
            $revenue_chart[] = [
                'month' => date('M Y', strtotime($month . '-01')),
                'revenue' => floatval($revenue)
            ];
        }

        return $revenue_chart;
    }

    public function getRecentOrders($limit = 5)
    {
        $sql = "SELECT code, customer_name, created_at, total, status 
                FROM orders 
                ORDER BY created_at DESC 
                LIMIT ?";
        return $this->fetchAll($sql, [$limit]);
    }

    public function getTopProducts($limit = 5)
    {
        $sql = "SELECT p.name, p.image, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                GROUP BY oi.product_id
                ORDER BY total_sold DESC
                LIMIT ?";
        return $this->fetchAll($sql, [$limit]);
    }
}
