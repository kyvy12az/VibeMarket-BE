-- Bảng lưu trữ giao dịch ZaloPay
CREATE TABLE IF NOT EXISTS `zalopay_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(40) NOT NULL COMMENT 'Mã đơn hàng từ bảng orders',
  `app_trans_id` VARCHAR(45) NOT NULL UNIQUE COMMENT 'Mã giao dịch format: yymmdd_orderCode',
  `zp_trans_id` BIGINT NULL COMMENT 'Mã giao dịch ZaloPay (trả về khi callback)',
  `amount` BIGINT NOT NULL COMMENT 'Số tiền giao dịch (VND)',
  `zp_trans_token` VARCHAR(255) NULL COMMENT 'Token giao dịch từ ZaloPay',
  `order_url` TEXT NULL COMMENT 'URL thanh toán ZaloPay',
  `status` ENUM('pending', 'success', 'failed') DEFAULT 'pending' COMMENT 'Trạng thái giao dịch',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_code` (`order_code`),
  INDEX `idx_app_trans_id` (`app_trans_id`),
  INDEX `idx_zp_trans_id` (`zp_trans_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Giao dịch thanh toán ZaloPay';
