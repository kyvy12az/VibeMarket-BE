<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $page_title ?? 'Admin'; ?> | VibeMarket Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $this->baseUrl('css/app.css?v=' . time()); ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="<?php echo $this->baseUrl('img/icons/k.png'); ?>" />
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>

<body>
    <div class="wrapper">
        <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>
        
        <div class="main">
            <?php require_once __DIR__ . '/../partials/navbar.php'; ?>
            
            <main class="content">
                <?php require_once __DIR__ . '/../' . $view . '.php'; ?>
            </main>
            
            <?php require_once __DIR__ . '/../partials/footer.php'; ?>
        </div>
    </div>

    <!-- Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo $this->baseUrl('js/app.js'); ?>"></script>
    
    <!-- Global Dropdown Handler -->
    <script>
    $(document).ready(function() {
        // Manual dropdown toggle handler for all pages
        $(document).on('click', '.dropdown-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close other dropdowns
            $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
            
            // Toggle this dropdown
            $(this).next('.dropdown-menu').toggleClass('show');
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').removeClass('show');
            }
        });
        
        // Close dropdown when clicking on a dropdown item
        $(document).on('click', '.dropdown-item', function() {
            $(this).closest('.dropdown-menu').removeClass('show');
        });
    });
    </script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
</body>

</html>
