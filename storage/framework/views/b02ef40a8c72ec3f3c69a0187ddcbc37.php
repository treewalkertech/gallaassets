<?php $__env->startSection('title', 'Plant Locations - List'); ?>
<?php $__env->startSection('page-style'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/datatables.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic border-top table" id="DataTables2025">
                    </table>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="viewRowDetails" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="row-title">Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8" id="name">-</dd>
                        <dt class="col-sm-4">Code</dt>
                        <dd class="col-sm-8" id="code">-</dd>
                        <dt class="col-sm-4">Address</dt>
                        <dd class="col-sm-8" id="address">-</dd>
                        <dt class="col-sm-4">City</dt>
                        <dd class="col-sm-8" id="city">-</dd>
                        <dt class="col-sm-4">Pincode</dt>
                        <dd class="col-sm-8" id="pincode">-</dd>
                        <dt class="col-sm-4">State</dt>
                        <dd class="col-sm-8" id="state">-</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8" id="status">-</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <?php echo $__env->make('content.partial.datatable', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script type="text/javascript">
        $(document).ready(function() {
            var tableHeaders = <?php echo $table_headers; ?>;
            console.log(tableHeaders);
            var filterData = {
                'status': {
                    'data': {
                        'all': 'ALL',
                        'active': 'ACTIVE',
                        'inactive': 'INACTIVE',
                        'pending': 'PENDING'
                    },
                    'filter_name': 'Filter By Status',
                }
            };
            var options = {
                url: "<?php echo e(route('locations.list')); ?>",
                createUrl: "<?php echo e(route('locations.create')); ?>",
                createPermissions: "<?php echo e(isset($createPermissions) ? $createPermissions : ''); ?>",
                fetchId: "FetchData",
                title: "Plant Locations",
                createTitle: 'Add Location',
                manuall_create: "<?php echo e($createPermissions ? 1 : ''); ?>",
                is_delete: "",
            };

            // getDataTableS(options, {}, tableHeaders);

            getDataTableS(options, filterData, tableHeaders);

            $(".addNewRecordBtn").click(function() {
                window.location.href = "<?php echo e(route('locations.create')); ?>";
            });

            // View row details
            window.viewRowDetails = function(url) {
                $.ajax(url, {
                        dataType: 'json'
                    })
                    .done(function(response) {
                        if (response.data) {
                            var d = response.data;
                            $('#viewRowDetails #row-title').text(d.location_name || '-');
                            $('#viewRowDetails #name').text(d.location_name || '-');
                            $('#viewRowDetails #code').text(d.location_code || '-');
                            $('#viewRowDetails #address').text(d.address || '-');
                            $('#viewRowDetails #city').text(d.city || '-');
                            $('#viewRowDetails #pincode').text(d.pincode || '-');
                            $('#viewRowDetails #state').text(d.state || '-');
                            $('#viewRowDetails #status').html((d.status == 1) ?
                                '<span class="badge bg-label-success">Active</span>' :
                                '<span class="badge bg-label-secondary">Inactive</span>');
                            $('#viewRowDetails').modal('show');
                        } else {
                            toastr.error('Failed to fetch details');
                        }
                    })
                    .fail(function() {
                        toastr.error('Failed to fetch details');
                    });
            }
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/gallamattressapp/resources/views/content/locations/list.blade.php ENDPATH**/ ?>