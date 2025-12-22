<?php $__env->startSection('title', ' Role and Permission - Form'); ?>
<?php $__env->startSection('page-style'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="row mx-0">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Set user role and permission</h5>
            </div>
            <div class="card-body">
                <form class="needs-validation" id="roleForm" novalidate>
                    <?php echo e(csrf_field()); ?>

                    <div class="row px-3 py-2">

                        <div class="mb-4 text-center">
                            <h3 class="role-title"><?php echo e($role_id ? 'Update Role' : 'Add New Role'); ?></h3>
                            <p>Set role permissions</p>
                        </div>

                        <!-- Role Name -->
                        <div class="col-md-6 col-12 fv-plugins-icon-container mb-4">
                            <h4 for="RoleName">Enter Role Name</h4>
                            <input type="text" id="RoleName" name="RoleName" class="form-control"
                                placeholder="Enter a role name" value="<?php echo e(isset($role_name) ? $role_name : ''); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a role name.
                            </div>
                        </div>

                        <!-- Role Type -->
                        <?php if($is_super_admin): ?>
                            <div class="col-md-6 col-12 fv-plugins-icon-container mb-4">
                                <h4 for="role_type">Role Type</h4>
                                <select id="role_type" name="role_type" class="form-select" required>
                                    <option value="">Select role type</option>
                                    <?php $__currentLoopData = $role_types ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($value); ?>"
                                            <?php echo e(isset($role_info->role_type) && $role_info->role_type == $value ? 'selected' : ''); ?>>
                                            <?php echo e($name ?? ''); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a role type.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Status -->
                        <div class="col-12 fv-plugins-icon-container mb-2">
                            <label class="form-label" for="Status">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="1" name="status" id="status"
                                    checked>
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div class="col-12 py-2">
                            <div>
                                <input type="text" id="searchInput" class="form-control my-3" placeholder="Search...">
                            </div>

                            <div class="table-responsive">
                                <table class="table-flush-spacing table" id="permissionTable">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium text-nowrap">
                                                Administrator Access
                                                <i class="bx bx-info-circle bx-xs" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" aria-label="Allows full access to the system"
                                                    data-bs-original-title="Allows full access to the system"></i>
                                            </td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="all"
                                                        id="selectAll">
                                                    <label class="form-check-label" for="selectAll">
                                                        Select All
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>

                                        <?php if($module_permission): ?>
                                            <?php $__currentLoopData = $module_permission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module_id => $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr>
                                                    <td class="fw-medium text-nowrap">
                                                        <div class="form-check me-lg-5 me-3">
                                                            <input class="form-check-input all_permissions" type="checkbox"
                                                                value="all">
                                                            <label class="form-check-label" for="all_permission">
                                                                <?php echo app('translator')->get('modules.' . $module_id); ?>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="row mx-0">
                                                            <?php $__currentLoopData = $permission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission_id => $permission_name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <div class="col-12 col-md-3 form-check mb-1">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        <?php if($module_id == 'roles' && $permission_id == 'create.roles'): ?> onclick="return false" <?php endif; ?>
                                                                        name="<?php echo e($module_id); ?>[]"
                                                                        value="<?php echo e($permission_id); ?>"
                                                                        <?php if(isset($grants_permission[$module_id][$permission_id]) &&
                                                                                $grants_permission[$module_id][$permission_id] == $permission_id): ?> checked <?php endif; ?>>
                                                                    <label class="form-check-label"
                                                                        for="<?php echo e($permission_id); ?>">
                                                                        <?php echo e($permission_name); ?>

                                                                    </label>
                                                                </div>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row">
                            <div class="col-12 text-end">
                                <button type="button" onclick="history.back()" class="btn btn-secondary me-2"
                                    aria-label="Back">
                                    Back
                                </button>
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <?php echo $__env->make('content.modals.addPermission', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script>
        $(document).ready(function() {

            // Form validation & AJAX submission
            const form = document.getElementById("roleForm");

            form.addEventListener("submit", function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (form.checkValidity()) {
                    let role_id = "<?php echo e(isset($role_id) ? $role_id : ''); ?>";

                    $.ajax({
                        type: "POST",
                        url: "<?php echo e(route('roles.save')); ?>/" + role_id,
                        data: $(form).serialize(),
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.message);
                                window.location.href = "<?php echo e(route('roles')); ?>";
                            } else {
                                toastr.error(response.message);
                            }
                        },
                        error: function(xhr) {
                            toastr.error("An error occurred while submitting the form.");
                        }
                    });
                }

                form.classList.add("was-validated");
            });

            // Search permissions
            $('#searchInput').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#permissionTable tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Select all permissions
            $('#selectAll').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('.table-flush-spacing tbody input[type="checkbox"]').prop('checked', isChecked);
            });

            // Update selectAll checkbox
            $('.table-flush-spacing tbody input[type="checkbox"]').on('change', function() {
                var allChecked = $('.table-flush-spacing tbody input[type="checkbox"]').length ===
                    $('.table-flush-spacing tbody input[type="checkbox"]:checked').length;
                $('#selectAll').prop('checked', allChecked);
            });

            // Module-level select all
            $('.all_permissions').on('change', function() {
                var isChecked = $(this).prop('checked');
                $(this).closest('tr').find('td input[type="checkbox"]').prop('checked', isChecked);
            });

            // Update module-level checkbox
            $('.table-flush-spacing tbody input[type="checkbox"]').on('change', function() {
                var $row = $(this).closest('tr');
                var allChecked = $row.find('td input[type="checkbox"]').not('.all_permissions').length ===
                    $row.find('td input[type="checkbox"]:checked').not('.all_permissions').length;
                $row.find('.all_permissions').prop('checked', allChecked);
            });

            // Initialize select2
            $("#role_type").select2({
                allowClear: true
            });

        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/gallamattressapp/resources/views/content/roles-and-permissions/create.blade.php ENDPATH**/ ?>