<?php $__env->startSection('title', ' Settings - View'); ?>
<?php $__env->startSection('page-style'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/fileinput.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/lightbox.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/datatables.bootstrap5.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
    <h4 class="mb-4 py-3">
        <span class="text-muted fw-light">Config Setting/</span>
        Management
    </h4>
    <div class="row">
        <div class="col-xl-3 col-lg-4 col-md-4 order-md-0 order-1 pe-0">
            <div class="nav-align-left">
                <ul class="nav nav-pills w-100" role="tablist">
                    <div class="card">
                        <div class="card-body">
                            <li class="nav-item py-1">
                                <a class="nav-link active" id="company_profile_link" role="tab" data-bs-toggle="tab"
                                    href="#company_profile">
                                    <span class="px-1 text-lg">
                                        <i class='bx bxs-buildings'></i></span>
                                    Company Profile</a>
                            </li>
                            <li class="nav-item py-1">
                                <a class="nav-link" id="productsetting_link" role="tab" data-bs-toggle="tab"
                                    href="#productsetting">
                                    <span class="px-1 text-lg">
                                        <i class='bx bx-spreadsheet'></i></span>
                                    Product Setting</a>
                            </li>

                            <li class="nav-item py-1">
                                <a class="nav-link" id="designation_link" role="tab" data-bs-toggle="tab"
                                    href="#designation">
                                    <span class="px-1 text-lg">
                                        <i class='bx bxs-user-rectangle'></i></span>
                                    Designation</a>
                            </li>



                            <li class="nav-item py-1">
                                <a class="nav-link" id="email_configuration_link" role="tab" data-bs-toggle="tab"
                                    href="#email_configuration">
                                    <span class="px-1 text-lg">
                                        <i class='bx bxs-envelope'></i></span>
                                    Email Configuration</a>
                            </li>


                        </div>
                    </div>
                </ul>
            </div>

        </div>
        <div class="col-xl-9 col-lg-8 col-md-8 order-0 order-md-1">
            <div class="tab-content px-0 pt-0">

                <div class="tab-pane fade show active" id="company_profile">
                    <?php echo $__env->make('content.settings.tabs.company_profile', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>

                
                <div class="tab-pane fade" id="productsetting">
                    <?php echo $__env->make('content.settings.tabs.product_setting', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>

                
                <div class="tab-pane fade" id="designation">
                    <?php echo $__env->make('content.settings.tabs.designation', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>


                <div class="tab-pane fade" id="email_configuration">
                    <?php echo $__env->make('content.settings.tabs.email_config', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>

            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <?php echo $__env->make('content.settings.scripts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('content.settings.modal.uploadCompanyLogo', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script src="<?php echo e(asset('assets/js/fileinput.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/tab-hash.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/jquery-repeater.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/lightbox.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
    <script>
        $(document).ready(function() {
            // Settings Globel form submit Ajax ------ Start -----------------
            var bsValidationForms = document.querySelectorAll(".save_setting_data");
            // Loop over them and prevent submission
            Array.prototype.slice.call(bsValidationForms).forEach(function(form) {
                form.addEventListener(
                    "submit",
                    function(event) {
                        event.preventDefault(); // Prevent default form submission behavior
                        if (!form.checkValidity()) {
                            event.stopPropagation();
                        } else {
                            var formData = new FormData(form);
                            // AJAX submission if validation passes
                            $.ajax({
                                type: 'POST',
                                url: "<?php echo e(route('settings.save')); ?>",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: formData,
                                contentType: false,
                                processData: false,
                                crossDomain: true,
                                async: true,
                                success: function(response) {
                                    // Show success notification
                                    if (response.success) {
                                        toastr.success(response.message);
                                        $("#uploadsCompanyDocuments").modal('hide');
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000);
                                    } else {
                                        toastr.error(response.message);
                                        setTimeout(function() {
                                            $("#uploadsCompanyDocuments").modal(
                                                'hide');
                                        }, 2000)
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // Handle server-side validation errors
                                    var errors = xhr.responseJSON.error;
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
            // Common form submit ------ End -----------------

            // Upload Company Logo Inputs ------ Start -----------------
            $(`#company_logo`).fileinput({
                // theme: 'fas',
                showUpload: false,
                showRemove: true,
                browseClass: "btn btn-primary",
                browseLabel: "Select Files",
                allowedFileExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
                'previewFileType': 'any',
                'autoOrientImage': false
            });
            $(`#company_brand_logo`).fileinput({
                // theme: 'fas',
                showUpload: false,
                showRemove: true,
                browseClass: "btn btn-primary",
                browseLabel: "Select Files",
                allowedFileExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
                'previewFileType': 'any',
                'autoOrientImage': false
            });

            // Upload Company Logo Inputs ------ End -----------------

        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/laundryApp/resources/views/content/settings/list.blade.php ENDPATH**/ ?>