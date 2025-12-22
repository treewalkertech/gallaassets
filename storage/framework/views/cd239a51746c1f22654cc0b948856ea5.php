<?php
    $width = $width ?? "200";
    $height = $height ?? "150";

?>
<?php if(config("company_logo")): ?>
    <img src="<?php echo e(asset(config("company_logo"))); ?>" width="<?php echo e($width); ?>" style="object-fit: contain"
        height="<?php echo e($height); ?>" alt="Company Logo">
<?php else: ?>
    <img src="<?php echo e(asset("logos/logo.png")); ?>" width="<?php echo e($width); ?>" style="object-fit: contain"
        height="<?php echo e($height); ?>" alt="Company Logo">
<?php endif; ?>
<?php /**PATH /var/www/html/laundryApp/resources/views/_partials/logo.blade.php ENDPATH**/ ?>