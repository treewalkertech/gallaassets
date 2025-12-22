<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    

    <div class="app-brand demo">
        <a href="<?php echo e(route('dashboard')); ?>" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="text-primary">
                    <?php echo $__env->make('_partials.logo-icon', ['width' => 25, 'withbg' => 'var(--bs-primary)'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold text-capitalize fs-6 ms-2">
                <?php echo e(config('company_brand_name')); ?>

            </span>

        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left bx-sm d-flex align-items-center justify-content-center"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    
    <ul id="menu-inner-list" class="menu-inner h-auto overflow-auto py-1">
        <?php $__currentLoopData = $menuData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            
            <?php if(isset($menu->menuHeader)): ?>
                <li class="menu-header small text-uppercase">
                    <span class="menu-header-text"><?php echo e(__($menu->menuHeader)); ?></span>
                </li>

                
            <?php elseif(isset($menu->module_id) && \App\Helpers\UtilityHelper::CheckModulePermissions($menu->module_id)): ?>
                <?php
                    $activeClass = null;
                    $currentRouteName = Route::currentRouteName();
                    $routeParts = explode('.', $currentRouteName);

                    if (in_array($menu->slug, $routeParts)) {
                        $activeClass = 'active';
                    } elseif (isset($menu->submenu)) {
                        if (is_array($menu->slug)) {
                            foreach ($menu->slug as $slug) {
                                if (str_starts_with($currentRouteName, $slug)) {
                                    $activeClass = 'active open';
                                }
                            }
                        } else {
                            if (str_starts_with($currentRouteName, $menu->slug)) {
                                $activeClass = 'active open';
                            }
                        }
                    }
                ?>

                <li class="menu-item <?php echo e($activeClass); ?>">
                    <a href="<?php echo e(isset($menu->slug) ? url($menu->slug) : 'javascript:void(0);'); ?>"
                        class="<?php echo e(isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>"
                        <?php if(!empty($menu->target)): ?> target="_blank" <?php endif; ?>>
                        <?php if(isset($menu->icon)): ?>
                            <i class="menu-icon tf-icons <?php echo e($menu->icon); ?>"></i>
                        <?php endif; ?>
                        <div><?php echo e(isset($menu->module_id) ? __('modules.' . $menu->module_id) : ''); ?></div>

                        <?php if(isset($menu->badge)): ?>
                            <div class="badge bg-<?php echo e($menu->badge[0]); ?> rounded-pill ms-auto"><?php echo e($menu->badge[1]); ?></div>
                        <?php endif; ?>
                    </a>

                    
                    <?php if(isset($menu->submenu)): ?>
                        <?php echo $__env->make('layouts.sections.menu.submenu', ['menu' => $menu->submenu], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</aside>
<?php /**PATH /var/www/html/laundryApp/resources/views/layouts/sections/menu/verticalMenu.blade.php ENDPATH**/ ?>