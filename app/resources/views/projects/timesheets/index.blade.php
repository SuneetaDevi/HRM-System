@extends('layouts.admin')
@section('page-title')
    {{ $project->project_name . __("'s Timesheet") }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">{{ __('Project') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.show', $project->id) }}">{{ ucwords($project->project_name) }}</a>
    </li>
    <li class="breadcrumb-item">{{ __('Timesheet') }}</li>
@endsection
@section('action-btn')
    <div class="row gy-3 justify-content-end align-items-center">
        <div class="col-auto weekly-dates-div text-end me-2">
            <a href="#" class="action-item previous"><i class="ti ti-arrow-left"></i></a>
            <span class="weekly-dates"></span>
            <input type="hidden" id="weeknumber" value="0">
            <input type="hidden" id="selected_dates">
            <a href="#" class="action-item next"><i class="ti ti-arrow-right"></i>
            </a>
        </div>
        @can('export timesheet')
            <div class="col-auto project_tasks_select text-end">
                <a class="btn btn-primary add-small" data-url="{{ route('timesheet.export', $project->id) }}"
                    data-bs-toggle="tooltip" data-title="{{ __('Export Timesheet') }}" title="{{ __('Export') }}"
                    data-ajax-popup="true">
                    <i class="ti ti-file-export me-2"></i>{{ __('Export Timesheet') }}
                </a>
            </div>
        @endcan
        @can('create timesheet')
            <div class="col-auto project_tasks_select text-end">
                <div class="p-0 dropdown btn btn-sm">
                    <a class="btn btn-primary add-small" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="true">
                        <i class="ti ti-plus me-2"></i>{{ __('Add Task on Timesheet') }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-right tasks-box" x-placement="bottom-end">
                        <div class="scrollbar-inner">
                            <div class="mh-280">
                                <div class="tasks-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="row">
                <div class="col-12">
                    <div class="border shadow-none card">
                        <div class="card-body table-border-style">
                            <div class="overflow-auto table-responsive project-timesheet">
                            </div>
                            <div class="text-center notfound-timesheet">
                                <div class="p-3 text-center empty-project-text min-h-300">
                                    <h5 class="pt-5">{{ __("We couldn't find any data") }}</h5>
                                    <p class="m-0">{{ __("Sorry we can't find any timesheet records on this week.") }}
                                    </p>
                                    <p class="m-0">{{ __('To add timesheet record go to Add Task on Timesheet') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script-page')
    <script>
        function ajaxFilterTimesheetTableView() {
            var mainEle = $('.project-timesheet');
            var notfound = $('.notfound-timesheet');
            var week = parseInt($('#weeknumber').val());
            var project_id = '{{ $project->id }}';
            var isowner = '';
            var data = {
                week: week,
                project_id: project_id,
            }

            $.ajax({
                url: '{{ route('filter.timesheet.table.view') }}',
                data: data,
                success: function(data) {
                    $('.weekly-dates-div .weekly-dates').text(data.onewWeekDate);
                    $('.weekly-dates-div #selected_dates').val(data.selectedDate);

                    $('.project_tasks_select .tasks-list .dropdown-item').remove();

                    $.each(data.sectiontasks, function(i, item) {

                        var optionhtml = '';

                        if (item.section_id != 0 && item.section_name != '' && item.tasks.length > 0) {
                            optionhtml +=
                                `<a href="#" class="dropdown-item select-sub-heading" data-tasks-count="` +
                                item.tasks.length + `">` + item.section_name + `</a>`;
                        }
                        $.each(item.tasks, function(ji, jitem) {
                            optionhtml +=
                                `<a href="#" class="dropdown-item select-task" data-task-id="` +
                                jitem.task_id + `">` + jitem.task_name + `</a>`;
                        });
                        $('.project_tasks_select .tasks-list').append(optionhtml);
                    });

                    if (data.totalrecords == 0) {
                        mainEle.hide();
                        notfound.css('display', 'block');
                    } else {
                        notfound.hide();
                        mainEle.show();
                    }
                    mainEle.html(data.html);
                }
            });
        }

        $(function() {
            ajaxFilterTimesheetTableView();
        });

        $(document).on('click', '.weekly-dates-div .action-item', function() {
            var weeknumber = parseInt($('#weeknumber').val());
            if ($(this).hasClass('previous')) {
                weeknumber--;
                $('#weeknumber').val(weeknumber);
            } else if ($(this).hasClass('next')) {
                weeknumber++;
                $('#weeknumber').val(weeknumber);
            }
            ajaxFilterTimesheetTableView();
        });
        // $(document).on('click', '.project-timesheet .tbody img[data-ajax-timesheet-popup="true"]', function(e) {
        // $(document).on('click', '[data-ajax-timesheet-popup="true"]', function(e) {
            // $(document).on('click', '[data-ajax-timesheet-popup="true"]', function(e) {
            $(document).on('click', '.project-timesheet .tbody tr img', function(e) {
            e.preventDefault();
            console.log('Image clicked!');
            var data = {};
            var url = $(this).data('url');
            var type = $(this).data('type');
            var date = $(this).data('date');
            var task_id = $(this).data('task-id');
            var user_id = $(this).data('user-id');

            data.date = date;
            data.task_id = task_id;

            if (user_id != undefined) {
                data.user_id = user_id;
            }
            // if (type == 'create') {
            //     var title = '{{ __('Create Timesheet') }}';
            //     data.project_id = '{{ $project->id }}';
            // } else if (type == 'edit') {
            //     var title = '{{ __('Edit Timesheet') }}';
            // }

            var title = '{{ __('Create Timesheet') }}';
                data.project_id = '{{ $project->id }}';
            $("#commonModal .modal-title").html(title + ` <small>(` + moment(date).format("ddd, Do MMM YYYY") +
                `)</small>`);

                console.log('Image clicked! before ajax');
                // $('#commonModal .body').html(data);
                //   $('#commonModal').modal('show');
            $.ajax({
                url: url,
                data: data,
                dataType: 'html',
                success: function(data) {
                    // $('#commonModal .modal-body').html(data);
                    $('#commonModal .body').html(data);
                    $('#commonModal').modal('show');
    
                    console.log('Image clicked in ajax!');
                    if ($('#date').length > 0) {
                        $('#date').daterangepicker({
                            singleDatePicker: true,
                            locale: {
                                format: 'YYYY-MM-DD'
                            }
                        });
                    }
                    $('#commonModal').on('change', 'input[name="day_type"]', function() {
                        var selectedDayType = $(this).val();
                        showDayType(selectedDayType);
                    });
                    var dayType = $('#commonModal').find('input[name="day_type"]:checked').val();
                    showDayType(dayType);
                    $('#commonModal').modal({
                        backdrop: 'static',
                        keyboard: false
                    });
                    console.log('Image clicked! end ajax');
                }
            });
        });

       

        function showDayType(type) {
            if (type === 'full_day') {
                $('.display-total-time span').text(
                    '{{ __('Total Time worked on this task') }} : ' + '08' +
                    ' {{ __('Hours') }} ' + '00' + ' {{ __('Minutes') }}');
                $('#commonModal').find('.half_day_type').addClass('d-none');
            } else {
                $('.display-total-time span').text(
                    '{{ __('Total Time worked on this task') }} : ' + '04' +
                    ' {{ __('Hours') }} ' + '00' + ' {{ __('Minutes') }}');
                $('#commonModal').find('.half_day_type').removeClass('d-none');
            }
        }

        $('.project_tasks_select .tasks-box').on('click', '.select-task', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var mainEle = $('.project-timesheet');
            var notfound = $('.notfound-timesheet');

            var task_id = $(this).attr('data-task-id');
            var selected_dates = $('#selected_dates').val();

            $.ajax({
                url: '{{ route('append.timesheet.task.html') }}',
                data: {
                    project_id: '{{ $project->id }}',
                    task_id: task_id,
                    selected_dates: selected_dates,
                },
                success: function(data) {
                    notfound.hide();
                    mainEle.show();
                    
                    $('.project-timesheet .tbody').append(data.html);
                    $('.project_tasks_select .tasks-list .select-task[data-task-id="' + task_id + '"]')
                        .remove();
       
                        $('.project-timesheet .tbody tr').each(function() {
        $(this).find('td').each(function() {
            // Check if the cell is empty (contains '-')
            if ($(this).text().trim() === '-') {
                // Replace '-' with an image tag
                $(this).html('<img class="task-time" data-ajax-timesheet-popup="true" src="{{ asset('assets/images/noun-not-available-1552133.png') }}">');
            }
        });
    });
                }
            });
        });
    </script>
@endpush
