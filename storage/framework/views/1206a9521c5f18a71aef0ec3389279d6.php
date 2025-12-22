<?php $__env->startSection('title', ' Users - Profile'); ?>
<?php $__env->startSection('page-style'); ?>
<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('content.modals.changePassword', ['email' => $user_info->email], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <!-- Basic Layout & Basic with Icons -->
    <div class="row g-4 mx-0">
        <div class="card mb-4">
            <div class="card-body">
                <small class="text-muted text-uppercase">User Info</small>
                <ul class="list-unstyled mb-4 mt-3">
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-user"></i><span class="fw-medium mx-2">Full
                            Name:</span>
                        <span><?php echo e(isset($user_info->fullname) ? $user_info->fullname : ''); ?></span>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-check"></i><span
                            class="fw-medium mx-2">Username:</span>
                        <span><?php echo e(isset($user_info->username) ? $user_info->username : ''); ?></span>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-star"></i><span
                            class="fw-medium mx-2">Status:</span>
                        <span><?php echo e(isset($user_info->status) ? 'Active' : 'Inactive'); ?></span>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-flag"></i><span class="fw-medium mx-2">User
                            Code:</span> <span><?php echo e(isset($user_info->user_code) ? $user_info->user_code : ''); ?></span></li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-flag"></i><span class="fw-medium mx-2">User
                            Type:</span>
                        <span><?php echo e(isset($user_info->is_super_admin) && $user_info->is_super_admin ? 'Super Admin' : 'Admin'); ?></span>
                    </li>
                </ul>
                <small class="text-muted text-uppercase">Contacts</small>
                <ul class="list-unstyled mb-4 mt-3">
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-phone"></i><span
                            class="fw-medium mx-2">Contact:</span>
                        <span><?php echo e(isset($user_info->contact) ? $user_info->contact : ''); ?></span>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class='bx bx-calendar'></i><span
                            class="fw-medium mx-2">Created Date:</span>
                        <span><?php echo e(isset($user_info->created_at) ? $user_info->created_at : ''); ?></span>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-envelope"></i><span
                            class="fw-medium mx-2">Email:</span>
                        <span><?php echo e(isset($user_info->email) ? $user_info->email : ''); ?></span>
                    </li>
                </ul>
                <small class="text-muted text-uppercase">Role Info</small>
                <ul class="list-unstyled mt-3 mb-0">
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-user text-primary me-2"></i>
                        <div class="d-flex flex-wrap"><span class="fw-medium me-2">Role
                                Name</span><span><?php echo e(isset($role_info->role_name) ? $role_info->role_name : ''); ?></span>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class="bx bx-star text-info me-2"></i>
                        <div class="d-flex flex-wrap"><span
                                class="fw-medium me-2">Status</span><span><?php echo e(isset($role_info->status) ? 'Active' : 'Inactive'); ?></span>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-3"><i class='bx bx-calendar text-info me-2'></i>
                        <div class="d-flex flex-wrap"><span class="fw-medium me-2">Created
                                Date</span><span><?php echo e(isset($role_info->created_at) ? $role_info->created_at : ''); ?></span>
                        </div>
                    </li>
                </ul>
                <button class="btn btn-primary d-none" data-bs-target="#change_password" data-bs-toggle="modal"
                    name="changePass" id="change_pass">Change Password</button>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <?php echo $__env->make('content.partial.datatable', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script type="text/javascript">
        $(document).ready(function() {
            var bsValidationForms = document.querySelectorAll(".add_permission_form");
            Array.prototype.slice.call(bsValidationForms).forEach(function(form) {
                form.addEventListener(
                    "submit",
                    function(event) {
                        event.preventDefault(); // Prevent default form submission behavior
                        if (!form.checkValidity()) {
                            event.stopPropagation();
                        } else {
                            //return false;
                            // AJAX submission if validation passes
                            $.ajax({
                                type: 'POST',
                                url: "<?php echo e(route('users.changePassword')); ?>",
                                data: $(form).serialize(),
                                success: function(response) {
                                    // Show success notification
                                    if (response.success) {
                                        toastr.success(response.message);
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000)
                                    } else {
                                        toastr.error(response.message);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // Handle server-side validation errors
                                    var errors = xhr.responseJSON.errors;
                                    if (errors) {
                                        toastr.error(errors.join('<br>'));
                                    } else {
                                        toastr.error(
                                            'An error occurred while submitting the form.'
                                        );
                                    }
                                }
                            });
                        }

                        form.classList.add("was-validated");
                    },
                    false
                );
            });
        }); // end jquery document dot write
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/laundryApp/resources/views/content/user-profile/profile.blade.php ENDPATH**/ ?>