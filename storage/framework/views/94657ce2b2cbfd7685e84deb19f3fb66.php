<?php
    $containerNav = $containerNav ?? "container-fluid";
    $navbarDetached = $navbarDetached ?? "";
    $user = \Auth::user();
    $role_info = \App\Helpers\UtilityHelper::getUserRoleInfo($user->role_id);
    $setting_permission = App\Helpers\UtilityHelper::CheckModulePermissions("config_settings", "view.config_settings");
?>

<!-- Navbar -->
<?php if(isset($navbarDetached) && $navbarDetached == "navbar-detached"): ?>
    <nav class="layout-navbar <?php echo e($containerNav); ?> navbar navbar-expand-xl <?php echo e($navbarDetached); ?> align-items-center bg-navbar-theme"
        id="layout-navbar">
<?php endif; ?>
<?php if(isset($navbarDetached) && $navbarDetached == ""): ?>
    <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="<?php echo e($containerNav); ?>">
<?php endif; ?>

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
<?php if(isset($navbarFull)): ?>
    <div class="navbar-brand app-brand demo d-none d-xl-flex me-4 py-0">
        <a href="<?php echo e(url("/")); ?>" class="app-brand-link gap-2">
            <span class="app-brand-logo demo"><?php echo $__env->make("_partials.macros", ["width" => 25, "withbg" => "var(--bs-primary)"], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?></span>
            <span class="app-brand-text demo menu-text fw-bold"><?php echo e(config("company_brand_name")); ?></span>
        </a>
    </div>
<?php endif; ?>

<!-- ! Not required for layout-without-menu -->
<?php if(!isset($navbarHideToggle)): ?>
    <div
        class="layout-menu-toggle navbar-nav align-items-xl-center me-xl-0<?php echo e(isset($menuHorizontal) ? " d-xl-none " : ""); ?> <?php echo e(isset($contentNavbar) ? " d-xl-none " : ""); ?> me-3">
        <a class="nav-item nav-link me-xl-4 px-0" href="javascript:void(0)">
            <i class="bx bx-menu bx-sm"></i>
        </a>
    </div>
<?php endif; ?>

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center">
        <div class="nav-item d-flex align-items-center">
            <div class="nav-item d-flex align-items-center me-2" data-bs-toggle="tooltip" data-bs-offset="0,4"
                data-bs-placement="right" data-bs-html="true" title="Easy Navigation ">
                <a class="" id="easy_dashboard_nav_link" href="javascript:;" data-bs-target="#easy_nav_nodal"
                    data-bs-toggle="modal">
                    <i class="bx bx-grid-alt bx-sm"></i>
                </a>
            </div>
            <div class="nav-item d-flex align-items-center">
                <i class="bx bx-search fs-4 lh-0"></i>
                <input type="text" class="form-control ps-sm-2 border-0 ps-1 shadow-none" placeholder="Search..."
                    aria-label="Search...">
            </div>
        </div>
    </div>
    <!-- /Search -->
    <ul class="navbar-nav align-items-center ms-auto flex-row">



        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center" href="javascript:void(0);"
                data-bs-toggle="dropdown">
                
                <div class="avatar-wrapper">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded-circle bg-label-warning">
                            <?php
                                echo mb_substr($user->fullname, 0, 1);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1 ms-1">
                    <h6 class="captitalize text-truncate m-0"><?php echo e($user->fullname); ?></h6>
                    
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <div class="d-flex">
                            
                            <div class="flex-grow-1">
                                <span
                                    class="fw-medium d-block"><?php echo e(isset($user->fullname) ? $user->fullname : ""); ?></span>
                                <small class="text-muted"><?php echo e(isset($user->username) ? $user->username : ""); ?></small>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="<?php echo e(route("profile", ["user_code" => $user->user_code])); ?>">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                    </a>
                </li>
                <?php if($setting_permission): ?>
                    <li>
                        <a class="dropdown-item" href="<?php echo e(route("settings")); ?>">
                            <i class='bx bx-cog me-2'></i>
                            <span class="align-middle">Settings</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <li>
                    <div class="dropdown-divider"></div>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo e(route("user-logout")); ?>">
                        <i class='bx bx-power-off me-2'></i>
                        <span class="align-middle">Log Out</span>
                    </a>
                </li>
            </ul>
        </li>
        <!--/ User -->
    </ul>
</div>

<?php if(!isset($navbarDetached)): ?>
    </div>
<?php endif; ?>
</nav>
<!-- / Navbar -->
<?php /**PATH /var/www/html/gallamattressapp/resources/views/layouts/sections/navbar/navbar.blade.php ENDPATH**/ ?>