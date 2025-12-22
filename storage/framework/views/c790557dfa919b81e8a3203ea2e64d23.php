<?php $__env->startSection('title', 'Inventory Management'); ?>
<?php $__env->startSection('page-style'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/datatables.bootstrap5.css')); ?>">
    <style>
        @media screen and (max-width: 768px) {
            .select2-container--default {
                margin-bottom: 10px !important;
            }

            .create-new {
                margin-bottom: 10px !important;
            }
        }
    </style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="d-none mb-3" id="errorBox"></div>
            <div class="card mb-4">
                <div class="card-widget-separator-wrapper">
                    <div class="card-body card-widget-separator">
                        <div class="row gy-4 gy-sm-1">
                            <div class="col-sm-6 col-lg-3">
                                <div
                                    class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-sm-0 pb-3">
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo e($inventoryOverview['total_inventory'] ?? '0'); ?>

                                        </h3>
                                        <p class="mb-0">Total Inventory</p>
                                    </div>
                                    <span class="badge bg-label-success me-sm-4 rounded p-2">
                                        <i class="bx bx-store-alt bx-sm"></i>
                                    </span>
                                </div>
                                <hr class="d-none d-sm-block d-lg-none me-4">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div
                                    class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-sm-0 pb-3">
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo e($inventoryOverview['total_new'] ?? '0'); ?>

                                        </h3>
                                        <p class="mb-0">Total Unused</p>
                                    </div>
                                    <span class="badge bg-label-warning me-lg-4 rounded p-2">
                                        <i class="bx bx-crown bx-sm"></i>
                                    </span>
                                </div>
                                <hr class="d-none d-sm-block d-lg-none">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div
                                    class="d-flex justify-content-between align-items-start border-end pb-sm-0 card-widget-3 pb-3">
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo e($inventoryOverview['total_clean'] ?? '0'); ?>

                                        </h3>
                                        <p class="mb-0">Total Clean</p>
                                    </div>
                                    <span class="badge bg-label-success me-sm-4 rounded p-2">
                                        <i class="bx bx-check-circle bx-sm"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div
                                    class="d-flex justify-content-between align-items-start border-end pb-sm-0 card-widget-3 pb-3">
                                    <div>
                                        <h3 class="mb-1">
                                            <?php echo e($inventoryOverview['total_dirty'] ?? '0'); ?>

                                        </h3>
                                        <p class="mb-0">Total Used </p>
                                    </div>
                                    <span class="badge bg-label-danger me-sm-4 rounded p-2">
                                        <i class="bx bx-error bx-sm"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <!--- Filters ------------ START ---------------->
                
                
                
                <!--- Filters ------------ END ---------------->
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic border-top table" id="DataTables2025">
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php $__env->stopSection(); ?>
<?php
    $is_export = 1;
?>
<?php $__env->startSection('page-script'); ?>
    <?php echo $__env->make('content.inventory.modal.bulkBondingProductImport', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('content.partial.datatable', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('content.common.scripts.daterangePicker', [
        'float' => 'right',
        'name' => 'masterTableDaterangePicker',
        'default_days' => 30,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script>
        $(document).ready(function() {
            var tableHeaders = <?php echo $table_headers; ?>;
            var options1 = {
                url: "<?php echo e(route('inventory.list')); ?>",
                createUrl: '<?php echo e(route('create.inventory')); ?>',
                createPermissions: "<?php echo e($createPermissions ?? ''); ?>",
                fetchId: "FetchData",
                title: "Inventory Management",
                createTitle: "Manually Create",
                displayLength: 30,
                is_import: "Upload Models",
                is_delete: "<?php echo e($deletePermissions ?? ''); ?>",
                delete_url: "<?php echo e(route('delete.inventory')); ?>",
                importUrl: "<?php echo e(route('create.inventory')); ?>",
                is_export: "Export",
                manuall_create: false,
            };
            var filterData = {
                'status': {
                    'data': {
                        '1': 'WRITTEN',
                        '0': 'PENDING',
                        'all': 'ALL',
                    },
                    'filter_name': 'Filter By Status',
                },
            };

            console.log("filterData:", filterData);

            getDataTableS(options1, filterData, tableHeaders, getStats);

            function getStats(params) {
                console.log("Applied filters:", params);
            }

            $(".addNewRecordBtn").click(function() {
                window.location.href = '<?php echo e(route('create.inventory')); ?>';
            });


            // ========== Export bonding data via POST form submit (works for file download) ============= START ==================
            let exportUrl = "<?php echo e(route('inventory.exportInventory')); ?>";

            $(".exportBtn").click(function() {
                const selectedDaterange = document.getElementById('selectedDaterange')?.value || '';
                const statusFilter = document.getElementById('statusFilter')?.value || '';

                // create form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = exportUrl;
                form.style.display = 'none';
                // optional: open in new tab (may be blocked by popup blockers)
                // form.target = '_blank';

                // CSRF token
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    '<?php echo e(csrf_token()); ?>';
                const _token = document.createElement('input');
                _token.type = 'hidden';
                _token.name = '_token';
                _token.value = token;
                form.appendChild(_token);

                // daterange
                const dr = document.createElement('input');
                dr.type = 'hidden';
                dr.name = 'daterange';
                dr.value = selectedDaterange;
                form.appendChild(dr);

                // status
                const st = document.createElement('input');
                st.type = 'hidden';
                st.name = 'status';
                st.value = statusFilter;
                form.appendChild(st);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            });
            // ========== Export bonding data via POST form submit (works for file download) ============= END ==================


            $(".bulkImportBtn").click(function() {
                $("#bulkProductImportModal").modal('show');
            });
        });

        // Delete row
        function deleteRow(url) {
            console.log("Delete URL:", url);
            if (!url) {
                alert('Permission denied!');
                return false;
            }
            if (!confirm("Are you sure you want to delete this item?")) {
                return false;
            }
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                method: 'POST',
                success: function(response) {

                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);

                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    toastr.error("Error: " + error);
                }
            });
        }
    </script>



<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/laundryApp/resources/views/content/inventory/list.blade.php ENDPATH**/ ?>