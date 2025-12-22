<?php
    $shotcuts_modules = [];
    $shotcuts_modules = App\Helpers\UtilityHelper::shortcutsNavigationModules();
?>
<div class="modal fade" id="easy_nav_nodal" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalCenterTitle">
                    <span><i class='bx bxs-right-arrow-circle bx-flip-vertical fs-3'></i></span>
                    Shortcuts Navigation
                    
                </h4>
                <button type="button" class="btn btn-label-danger p-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">

                    <?php if($shotcuts_modules && count($shotcuts_modules) > 0): ?>
                        <?php $__currentLoopData = $shotcuts_modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="col-md-6 col-12 p-3">
                                <div class="custom-option custom-option-icon">
                                    <div class="d-flex flex-column align-items-center p-3">
                                        <a href="<?php echo e($data["url"] ?? ""); ?>" title="<?php echo e($data["title"] ?? ""); ?>"
                                            class="text-center">
                                            <span class="mb-2">
                                                <i
                                                    class="<?php echo e($data["icon"] ?? ""); ?> fs-3 text-primary p-3 bg-label-primary rounded-circle mb-2"></i>
                                            </span>
                                            <p class="card-title"><?php echo e($data["title"] ?? ""); ?></p>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <div class="col-md-12 col-12 p-3">
                            <div class="custom-option custom-option-icon">
                                <div class="d-flex flex-column align-items-center p-3">
                                    <a href="#" class="text-center">
                                        <span class="mb-2">
                                            <i class="bx bx-info-circle fs-1 text-danger mb-2"></i>
                                        </span>
                                        <div class="alert alert-warning" role="alert">
                                            <h5 class="alert-heading mb-1">Permission denied!</h5>
                                            <span>You have no permission to use Shortcuts</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                
            </div>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/laundryApp/resources/views/content/common/easy-nav-modal.blade.php ENDPATH**/ ?>