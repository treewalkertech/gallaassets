<?php $__env->startSection('title', 'Create Product'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="d-flex mb-1">Create New Items</h4>
                    <div class="d-flex justify-content-start form-label-class">
                        <p class="card-subtitle text-warning my-0">Fields marked with <span class="text-danger">*</span> are
                            mandatory</p>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="<?php echo e(route('products.save')); ?>" id="product-create-form"
                        enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="row">

                            <!-- Items Name -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="product_name" class="form-label">Items Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="product_name" name="product_name"
                                        placeholder="Enter Items name"
                                        class="form-control <?php $__errorArgs = ['product_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required
                                        value="<?php echo e(old('product_name')); ?>">
                                    <?php $__errorArgs = ['product_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>

                            <!-- SKU -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" id="sku" name="sku" placeholder="Enter SKU"
                                        class="form-control <?php $__errorArgs = ['sku'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required
                                        value="<?php echo e(old('sku')); ?>">
                                    <?php $__errorArgs = ['sku'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <input type="text" id="description" name="description" class="form-control"
                                        placeholder="Enter description" value="<?php echo e(old('description')); ?>">
                                    <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>

                            <!-- Selected Location -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Selected Location</label>
                                    <select id="location_id" name="location_id" class="form-select">
                                        <option value="">Select Location</option>
                                        <?php $__currentLoopData = $locations_info ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($location->location_id); ?>"
                                                <?php echo e(isset($loginuser_location_id) && $loginuser_location_id == $location->location_id ? 'selected' : ''); ?>>
                                                <?php echo e($location->location_name); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Expected Life Cycles -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="expected_life_cycles" class="form-label">Expected Life Cycles</label>
                                    <input type="number" id="expected_life_cycles" name="expected_life_cycles"
                                        class="form-control" placeholder="Enter expected life cycles"
                                        value="<?php echo e(old('expected_life_cycles')); ?>">
                                    <?php $__errorArgs = ['expected_life_cycles'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <div class="invalid-feedback"><?php echo e($message); ?></div>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>



                            <!-- Status -->
                            <div class="col-md-6 col-12">
                                <div class="mb-3">
                                    <label for="qc_status" class="form-label">Status</label>
                                    <select id="qc_status" name="qc_status" class="form-select">
                                        <option value="1">
                                            Active</option>
                                        <option value="0">Inactive
                                        </option>
                                    </select>
                                </div>
                            </div>

                        </div>
                        <div class="my-3 text-end">
                            <button type="submit" class="btn btn-primary" id="submit-button">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <script>
        $(document).ready(function() {
            // Setup jQuery validation for product form
            $("#product-create-form").validate({
                rules: {
                    product_name: {
                        required: true,
                        maxlength: 150
                    },
                    sku: {
                        required: true,
                        maxlength: 100
                    },
                    size: {
                        required: true,
                        maxlength: 150
                    },
                    rfid_tag: {
                        required: true,
                        maxlength: 150
                    },
                    quantity: {
                        number: true,
                        min: 0
                    }
                },
                messages: {
                    product_name: {
                        required: 'Please enter product name',
                        maxlength: 'Product name cannot exceed 150 characters'
                    },
                    sku: {
                        required: 'Please enter SKU',
                        maxlength: 'SKU cannot exceed 100 characters'
                    },
                    size: {
                        required: 'Please enter size',
                        maxlength: 'Size cannot exceed 150 characters'
                    },
                    rfid_tag: {
                        required: 'Please enter RFID tag',
                        maxlength: 'RFID tag cannot exceed 150 characters'
                    },
                    quantity: {
                        number: 'Quantity must be a number',
                        min: 'Quantity cannot be negative'
                    }
                },
                submitHandler: function(form) {
                    // Disable submit button and show loading text
                    $("#submit-button").attr('disabled', true).html('Saving...');

                    var formData = new FormData(form);

                    $.ajax({
                        url: $(form).attr('action'),
                        type: $(form).attr('method'),
                        data: formData,
                        contentType: false,
                        processData: false,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.message ||
                                    'Product saved successfully');
                                $("#submit-button").html('Saved');
                                setTimeout(function() {
                                    // Redirect or reload page
                                    window.location.href = response.return_url ||
                                        "<?php echo e(route('products')); ?>";
                                }, 1500);
                            } else {
                                $("#submit-button").attr('disabled', false).html(
                                    'Save');
                                toastr.error(response.message || 'Failed to save product');
                            }
                        },
                        error: function(xhr) {
                            $("#submit-button").attr('disabled', false).html(
                                'Save');
                            var errors = xhr.responseJSON?.errors;
                            if (errors) {
                                // Show validation errors returned from server
                                $.each(errors, function(key, val) {
                                    toastr.error(val[0]);
                                });
                            } else {
                                toastr.error('An unexpected error occurred');
                            }
                        }
                    });
                }
            });
        });
        // Function to auto-generate a random RFID tag
        function autoGenerateRFIDTag() {
            let productName = $('#product_name').val().trim();
            let sku = $('#sku').val().trim();
            let size = $('#size').val().trim();
            if (!productName || !sku || !size) {
                toastr.warning('Please enter Product Name, SKU and Size before generating RFID tag');
                return;
            }
            // Simple random tag generation logic (can be customized as needed)
            let randomTag = productName.substring(0, 3).toUpperCase() + '-' +
                sku.substring(0, 3).toUpperCase() + '-' +
                size.substring(0, 3).toUpperCase() + '-' +
                Math.random().toString(36).substring(2, 8).toUpperCase();
            $('#rfid_tag').val(randomTag);

        }
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/laundryApp/resources/views/content/products/create.blade.php ENDPATH**/ ?>