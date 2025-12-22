<!DOCTYPE html>
<html class="light-style layout-navbar-fixed layout-compact layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="<?php echo e(asset("/assets") . "/"); ?>" data-base-url="<?php echo e(url("/")); ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title><?php echo $__env->yieldContent("title"); ?> | <?php echo e(config("company_name") ?? config("variables.organizationName")); ?></title>
    <meta name="description"
        content="<?php echo e(config("variables.templateDescription") ? config("variables.templateDescription") : ""); ?>" />
    <meta name="keywords"
        content="<?php echo e(config("variables.templateKeyword") ? config("variables.templateKeyword") : ""); ?>">
    <!-- laravel CRUD token -->
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <!-- Canonical SEO -->
    <link rel="canonical" href="<?php echo e(config("variables.productPage") ? config("variables.productPage") : ""); ?>">
    <!-- Favicon assets/img/favicon/favicon.ico -->
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset(config("company_brand_logo"))); ?>" />

    <!-- Include Styles -->
    <?php echo $__env->make("layouts/sections/styles", \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Include Scripts for customizer, helper, analytics, config -->
    <?php echo $__env->make("layouts/sections/scriptsIncludes", \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <style>
        .menu-vertical {
            width: 17.25rem;
        }

        .app-brand .layout-menu-toggle i {
            color: #fff;
            width: 1.5rem;
            height: 1.5rem;
            transition: all .3s ease-in-out;
            line-height: 1.05;
        }
    </style>
</head>

<body>
    <!-- Layout Content -->
    <?php echo $__env->yieldContent("layoutContent"); ?>
    <!--/ Layout Content -->

    <!-- Include Scripts -->
    <?php echo $__env->make("layouts/sections/scripts", \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->yieldContent("page-scripts"); ?>

    <?php if(session("error")): ?>
        <script>
            toastr.error('<?php echo e(session("error")); ?>');
        </script>
    <?php endif; ?>
    <?php if(session("success")): ?>
        <script>
            toastr.success('<?php echo e(session("success")); ?>');
        </script>
    <?php endif; ?>
    <?php if(session("warning")): ?>
        <script>
            toastr.warning('<?php echo e(session("warning")); ?>');
        </script>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            // Set initial sidebar state from localStorage
            var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            setSidebarState(collapsed);

            // Handle sidebar toggle click
            $('.layout-menu-toggle').click(function() {
                collapsed = !collapsed;
                setSidebarState(collapsed);
                localStorage.setItem('sidebarCollapsed', collapsed);
            });

            // Enable: Handle hover to temporarily expand sidebar
            $('.layout-menu').hover(
                function() {
                    // Mouse enters, and sidebar is collapsed
                    if (collapsed) {
                        $('.layout-navbar-fixed').removeClass('layout-menu-collapsed').addClass(
                            'layout-menu-expanded');
                    }
                },
                function() {
                    // Mouse leaves, return to collapsed state if needed
                    if (collapsed) {
                        $('.layout-navbar-fixed').addClass('layout-menu-collapsed').removeClass(
                            'layout-menu-expanded');
                    }
                }
            );

            // Helper function to set sidebar state
            function setSidebarState(collapsed) {
                if (collapsed) {
                    $('.layout-navbar-fixed').addClass('layout-menu-collapsed').removeClass(
                        'layout-menu-expanded');
                } else {
                    $('.layout-navbar-fixed').removeClass('layout-menu-collapsed').addClass(
                        'layout-menu-expanded');
                }
            }
        });
    </script>

</body>

</html>
<?php /**PATH /var/www/html/gallamattressapp/resources/views/layouts/commonMaster.blade.php ENDPATH**/ ?>