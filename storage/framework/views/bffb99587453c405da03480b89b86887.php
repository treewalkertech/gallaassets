<?php $__env->startSection('title', isset($location_id) ? 'Locations - Edit' : 'Locations - Create'); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title"><?php echo e(isset($location_id) ? 'Update Location' : 'Add New Location'); ?></h5>
                </div>
                <div class="card-body">
                    <form class="needs-validation" novalidate id="locationForm">
                        <?php echo e(csrf_field()); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Location Name</label>
                                <input type="text" name="location_name" id="location_name" class="form-control"
                                    value="<?php echo e($info->location_name ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter location name</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Location Code</label>
                                <input type="text" class="form-control" value="<?php echo e($info->location_code ?? ''); ?>"
                                    disabled>
                                <small class="text-muted">Automatically generated on save</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo e($info->address ?? ''); ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="<?php echo e($info->city ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" class="form-control"
                                    value="<?php echo e($info->pincode ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control" value="<?php echo e($info->state ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label d-block">Status Is Active</label>
                                <div class="form-check form-switch">
                                    <input type="checkbox" id="status" name="status" class="form-check-input"
                                        value="1" <?php echo e(isset($info) && $info->status == 0 ? '' : 'checked'); ?>>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="button" onclick="history.back()" class="btn btn-secondary me-2">Back</button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">Submit</button>
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
            var form = $('#locationForm');
            form.on('submit', function(e) {
                e.preventDefault();

                if (!form[0].checkValidity()) {
                    form.addClass('was-validated');
                    return;
                }

                var submitBtn = $('#submitBtn');
                submitBtn.prop('disabled', true).text('Submitting...');
                var id = "<?php echo e($location_id ?? ''); ?>";
                var url = "<?php echo e(route('locations.save')); ?>" + (id ? '/' + id : '');

                var data = form.serialize();
                // add status explicitly because unchecked checkbox won't send value
                if ($('#status').is(':checked')) {
                    data += '&status=1';
                } else {
                    data += '&status=0';
                }

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: data,
                    success: function(resp) {
                        if (resp.success) {
                            toastr.success(resp.message || 'Saved successfully');
                            window.location.href = "<?php echo e(route('locations')); ?>";
                        } else {
                            toastr.error(resp.message || 'Save failed');
                            submitBtn.prop('disabled', false).text('Submit');
                        }
                    },
                    error: function(xhr) {
                        var errors = xhr.responseJSON?.errors;
                        if (errors) {
                            toastr.error(errors.join('<br>'));
                        } else {
                            toastr.error(xhr.responseJSON?.message || 'An error occurred');
                        }
                        submitBtn.prop('disabled', false).text('Submit');
                    }
                });
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/gallamattressapp/resources/views/content/locations/create.blade.php ENDPATH**/ ?>