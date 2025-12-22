<?php $__env->startSection('title', ' Users - Activity'); ?>
<?php $__env->startSection('page-style'); ?>
    
    <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/datatables.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-6">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title"><?php echo e($user->fullname); ?></h5>
                    
                    <div>
                        <div class="row">
                            <div class="col-md-12 col-12 mb-6">
                                <label for="Label Name">Filter By Date Range</label>
                                <div class="input-group date">
                                    <input class="form-control" type="text" name="userActivityDaterangePicker"
                                        placeholder="DD/MM/YY" id="userActivityDaterangePicker" />
                                    <span class="input-group-text">
                                        <i class='bx bxs-calendar'></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body px-3 my-4">
                    <ul class="timeline mb-0">

                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <script src="<?php echo e(asset('assets/js/fileinput.min.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/js/custom-js.js')); ?>"></script>
    <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
    <?php echo $__env->make('content.common.scripts.daterangePicker', [
        'float' => 'left',
        'name' => 'userActivityDaterangePicker',
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script type="text/javascript">
        $(document).ready(function() {
            console.log("user activit");
            flatpickr(".datetime_picker", {
                weekNumbers: true,
                defaultDate: new Date()

            });

            let dateRange = $("#userActivityDaterangePicker")

            function getData() {
                $(".timeline").html();


                $.ajax({
                    url: '<?php echo e(route('users.activityLogs')); ?>',
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr(
                            'content')
                    },
                    data: {
                        user_code: "<?php echo e($user_code); ?>",
                        date: dateRange.val()
                    },

                    success: function(response) {
                        console.log("response 12", response);

                        loadActivity(response.data);
                        if (response?.success) {
                            // toastr.success(response?.message);
                        } else {
                            toastr.error(response?.message ??
                                'Unable to load Activity!');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error during fetching user activity:",
                            error);
                        loadActivity(null);
                        toastr.error("Error during activity");
                    }
                });
            }

            function loadActivity(data) {
                console.log("data", data);
                let html = '';
                data?.forEach(activity => {
                    html += `<li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-3">
                                    <h6 class="mb-0">${activity?.message}</h6>
                                    <small class="text-muted">${activity?.datetime}</small>
                                </div>
                                <p class="mb-2">
                                </p>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="badge bg-lighter rounded d-flex align-items-center">
                                        <i class="bx bxl-windows bx-xs text-info me-4"></i>
                                        <span class="h6 mb-0 text-body">${activity?.application}</span>
                                    </div>
                                </div>
                            </div>
                        </li>`;
                });

                $(".timeline").html(html);
                $(".card-title").text('TimeLine for - ' + "<?php echo e($user->fullname); ?>");

            }

            dateRange.on("change", function() {
                getData();
            });

            getData()
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/gallamattressapp/resources/views/content/users/user-activity.blade.php ENDPATH**/ ?>