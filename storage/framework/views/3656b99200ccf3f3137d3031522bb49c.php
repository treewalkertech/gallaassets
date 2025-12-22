<?php $__env->startSection("title", "Not Authorized"); ?>

<?php $__env->startSection("page-style"); ?>
    <!-- Page -->
    <link rel="stylesheet" href="<?php echo e(asset("assets/vendor/css/pages/page-misc.css")); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection("content"); ?>
    <!-- Error -->
    <div class="container-xxl container-p-y">
        
        <div class="misc-wrapper">
            <h2 class="mb-2 mx-2">You are not authorized!</h2>
            <p class="mb-4 mx-2">You do not have permission to view this page using the credentials that you have provided
                while login. <br> Please contact your site administrator.</p>
            <a href="" class="btn btn-primary">Back to home</a>
            <div class="mt-5">
                <img src="https://demos.themeselection.com/sneat-bootstrap-html-laravel-admin-template/demo/assets/img/illustrations/girl-with-laptop-light.png"
                    alt="page-misc-not-authorized-light" width="450" class="img-fluid"
                    data-app-light-img="illustrations/girl-with-laptop-light.png"
                    data-app-dark-img="illustrations/girl-with-laptop-dark.png">
            </div>
        </div>
    </div>
    <!-- /Error -->
<?php $__env->stopSection(); ?>

<?php echo $__env->make("layouts/contentNavbarLayout", \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/laundryApp/resources/views/content/auth/not-authorised.blade.php ENDPATH**/ ?>