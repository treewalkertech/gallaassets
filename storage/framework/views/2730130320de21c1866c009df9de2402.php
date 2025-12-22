
<div class="card mb-3">
    <div class="card-header">
        <div class="d-flex justify-content-between my-0 py-0">
            <h4 class="card-title"><span><i class='bx bx-spreadsheet'></i></span>Product Setting</h4>
        </div>
    </div>
    <div class="card-body">
        <div class="py-2">
            <p><strong class="fs-5">Product Stages</strong></p>
        </div>
        
        <form class="needs-validation save_setting_data py-2" novalidate id="save_product_stage_form">
            <?php echo e(csrf_field()); ?>

            <input type="hidden" name="submit_form_name" value="save_product_process_stages">
            <input type="hidden" name="setting_key" value="product_process_stages">
            <input type="hidden" name="setting_key_name" value="Product Process Stages">
            <div class="table-responsive">
                <table class="mt-2 table">
                    <thead>
                        <tr>
                            <th class="text-nowrap py-2">Label Name</th>
                            <th class="text-nowrap py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="product_stage_table_body">
                        <?php if(!empty($product_process_stages)): ?>
                            <?php $__currentLoopData = $product_process_stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $values): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <input type="text" class="form-control" value="<?php echo e($values['name']); ?>"
                                            name="product_process_stages[<?php echo e($key); ?>][name]" readonly>
                                        <input type="hidden" class="product_stage" value="<?php echo e($values['value']); ?>"
                                            name="product_process_stages[<?php echo e($key); ?>][value]">
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-danger btn-icon delete_row mx-2 mb-1 px-2"
                                            disabled>
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </form>
    </div>
</div>


<div class="card mb-3">
    <h4 class="card-header"><span><i class='bx bx-spreadsheet'></i></span>Product Status</h4>
    <div class="card-body">
        <div class="py-2">
            <p><strong class="fs-5">Product Status</strong></p>
        </div>
        
        <form class="needs-validation save_setting_data py-2" novalidate id="save_product_status_form">
            <?php echo e(csrf_field()); ?>

            <input type="hidden" name="submit_form_name" value="save_product_status">
            <input type="hidden" name="setting_key" value="product_status">
            <input type="hidden" name="setting_key_name" value="Product Status">
            <div class="table-responsive">
                <table class="mt-2 table">
                    <thead>
                        <tr>
                            <th class="text-nowrap py-2">Label Name</th>
                            <th class="text-nowrap py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="product_status_table_body">
                        <?php if(!empty($product_status)): ?>
                            <?php $__currentLoopData = $product_status; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $values): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <input type="text" class="form-control" value="<?php echo e($values['name']); ?>"
                                            name="product_status[<?php echo e($key); ?>][name]" readonly>
                                        <input type="hidden" class="product_status" value="<?php echo e($values['value']); ?>"
                                            name="product_status[<?php echo e($key); ?>][value]">
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-danger btn-icon delete_row mx-2 mb-1 px-2"
                                            disabled>
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </form>
    </div>
</div>




<div class="card mb-3">
    <h4 class="card-header"><span><i class='bx bx-spreadsheet'></i></span>Product Defect Points</h4>
    <div class="card-body">
        <?php if(!empty($product_process_stages)): ?>
            <form class="needs-validation save_setting_data py-2" novalidate id="save_product_defect_points_form">
                <?php echo e(csrf_field()); ?>

                <input type="hidden" name="submit_form_name" value="save_product_defect_points">
                <input type="hidden" name="setting_key" value="product_defect_points">
                <input type="hidden" name="setting_key_name" value="Product Defect Points">
                <?php $__currentLoopData = $product_process_stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stageKey => $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $stage_name = $stage['name'];
                        $stage_key = $stage['value'];
                    ?>

                    <div class="stage-block py-2" data-stage="<?php echo e($stage_key); ?>">
                        <p><strong class="fs-5"><?php echo e($stage_name); ?></strong></p>

                        <div class="d-flex justify-content-between my-0 py-0">
                            <input type="text" class="form-control product_defect_points_labelname me-3"
                                placeholder="Enter Defect Point">
                            <button class="btn btn-primary btn-sm add_new_defect_label" type="button">
                                <i class="bx bx-plus me-1"></i>Add
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="mt-2 table">
                                <thead>
                                    <tr>
                                        <th class="text-nowrap py-2">Label Name</th>
                                        <th class="text-nowrap py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="product_defect_points_table_body">
                                    <?php if(!empty($product_defect_points[$stage_key])): ?>
                                        <?php $__currentLoopData = $product_defect_points[$stage_key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pointIndex => $pointValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            
                                            <tr>
                                                <td class="text-nowrap">
                                                    <input type="text" class="form-control"
                                                        value="<?php echo e($pointValue['name']); ?>"
                                                        name="product_defect_points[<?php echo e($stage_key); ?>][<?php echo e($pointIndex); ?>][name]">

                                                    <input type="hidden" class="form-control"
                                                        value="<?php echo e($pointValue['value']); ?>"
                                                        name="product_defect_points[<?php echo e($stage_key); ?>][<?php echo e($pointIndex); ?>][value]">
                                                </td>
                                                <td class="text-nowrap">
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger btn-icon delete_row mx-2 mb-1 px-2"
                                                        disabled>
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div class="py-2 text-end">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/laundryApp/resources/views/content/settings/tabs/product_setting.blade.php ENDPATH**/ ?>