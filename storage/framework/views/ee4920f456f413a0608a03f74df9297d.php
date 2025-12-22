<?php
    $width = $width ?? "200";
    $height = $height ?? "150";

?>

<?php if(config("company_brand_logo")): ?>
    <img src="<?php echo e(asset(config("company_brand_logo"))); ?>" width="<?php echo e($width); ?>" style="object-fit: contain"
        height="<?php echo e($height); ?>" alt="Brand logo">
<?php else: ?>
    <img src="<?php echo e(asset("logos/logo-icon.png")); ?>" width="<?php echo e($width); ?>" style="object-fit: contain"
        height="<?php echo e($height); ?>" alt="Brand logo">
<?php endif; ?>
<?php /**PATH /var/www/html/laundryApp/resources/views/_partials/logo-icon.blade.php ENDPATH**/ ?>