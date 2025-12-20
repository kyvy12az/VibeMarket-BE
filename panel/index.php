<?php

/**
 * VibeMarket Admin Panel - MVC Front Controller
 * 
 * This file bootstraps the MVC application
 */

// Error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define base path
define('BASE_PATH', __DIR__);

// Load core classes
require_once BASE_PATH . '/core/App.php';
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/Model.php';
require_once BASE_PATH . '/core/Session.php';

// Start session
Session::start();

// Initialize and run the application
$app = new App();