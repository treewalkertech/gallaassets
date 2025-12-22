<!-- BEGIN: Vendor JS-->
<script src="<?php echo e(asset('assets/vendor/libs/jquery/jquery.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/popper/popper.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/js/bootstrap.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')); ?>"></script>

<?php echo $__env->yieldContent('vendor-script'); ?>
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->

<script src="<?php echo e(asset('assets/js/jquery.validate.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/jquery.form.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/toastr.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/moment.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/daterangepicker.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/tagify.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/ajax-request.js')); ?>"></script>
<!-- END: Theme JS-->
<!-- Pricing Modal JS-->
<?php echo $__env->yieldPushContent('pricing-script'); ?>
<!-- END: Pricing Modal JS-->
<!-- BEGIN: Page JS-->
<?php echo $__env->yieldContent('page-script'); ?>
<!-- END: Page JS-->

<?php /**PATH /var/www/html/gallamattressapp/resources/views/layouts/sections/scripts.blade.php ENDPATH**/ ?>