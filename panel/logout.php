<?php
session_start();
session_destroy();
header('Location: /VIBE_MARKET_BACKEND/VibeMarket-BE/panel/login.php?msg=logged_out');
exit;
