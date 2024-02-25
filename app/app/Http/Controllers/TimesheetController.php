<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEmployee;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use App\Models\Project;
use App\Models\Utility;
use App\Models\Timesheet;
use App\Models\ProjectTask;
use App\Models\ProjectUser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class TimesheetController extends Controller
{
    public function timesheetView(Request $request, $project_id)
    {
        $authuser = Auth::user();
        if (auth()->user()->can('manage timesheet')) {
            $project_ids = $authuser->projects()->pluck('project_id')->toArray();
            if (in_array($project_id, $project_ids)) {
                $project = Project::where('id', $project_id)->first();
                return view('projects.timesheets.index', compact('project'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetExport($id)
    {
        if (auth()->user()->can('export timesheet')) {
            $project               = Project::find($id);
            $projectEmployee       = ProjectUser::join('users', 'users.id', 'project_users.user_id')->where('users.type', '!=', 'company')->where('users.type', '!=', 'client')->where('project_users.project_id', $project->id)->pluck('users.name', 'users.id')->toArray();
            return view('projects.timesheets.export', compact('project', 'projectEmployee'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetExportStore(Request $request, $id)
    {
        if (auth()->user()->can('export timesheet')) {
            $request->validate([
                'date'      => 'required',
                'user'      => 'required|integer|exists:users,id',
            ]);
            $dateArray              = [];
            $employeeData           = Employee::where('user_id', $request->user)->first();
            $date                   = explode(' to ', $request->date);
            $project                = Project::find($id);
            $manager                = User::find($project->created_by);
            if ($date && $employeeData) {
                $firstDay           = Carbon::parse($date[0]);
                $lastDay            = Carbon::parse($date[1]);
                for ($currentDay = $firstDay; $currentDay->lte($lastDay); $currentDay->addDay()) {
                    $dateArray[]    = $currentDay->copy();
                }
                $leaves = Leave::where('employee_id', $employeeData->id)
                    ->whereDate('start_date', '<=', end($dateArray)->format('Y-m-d'))
                    ->whereDate('end_date', '>=', $dateArray[0]->format('Y-m-d'))
                    ->get();

                $leaveInfo = [];
                foreach ($dateArray as $date) {
                    $leaveInfo[$date->format('Y-m-d')] = [
                        'on_leave' => false,
                        'description' => null,
                    ];

                    foreach ($leaves as $leave) {
                        if ($date->between($leave->start_date, $leave->end_date)) {
                            $leaveInfo[$date->format('Y-m-d')] = [
                                'on_leave' => true,
                                'description' => $leave->description,
                            ];
                            break;
                        }
                    }
                }
                $timesheets             = Timesheet::select('timesheets.*')
                    ->join('projects', 'projects.id', 'timesheets.project_id')
                    ->join('project_users', 'projects.id', 'project_users.project_id')
                    ->join('employees', 'employees.user_id', 'project_users.user_id')
                    ->where('employees.id', $employeeData->id)
                    ->where('timesheets.project_id', $project->id)
                    ->whereDate('date', '>=', $dateArray[0]->format('Y-m-d'))->whereDate('date', '<=', end($dateArray)->format('Y-m-d'))
                    ->orderBy('date')
                    ->get();
                foreach ($timesheets as &$timesheet) {
                    $date = Carbon::parse($timesheet->date)->format('Y-m-d');
                    $timesheet->on_leave = $leaveInfo[$date]['on_leave'];
                }
                $pdf                    = Pdf::setOption([
                    'isPhpEnabled'      => true,
                    'compress'          => true,
                ])->loadView('projects.timesheets.timesheet-pdf', [
                    'timesheets'        => $timesheets,
                    'project'           => $project,
                    'manager'           => $manager,
                    'employeeData'      => $employeeData,
                    'dateArrays'        => $dateArray,
                    'firstDay'          => $firstDay,
                    'lastDay'           => $lastDay,
                ]);
                return $pdf->download($project->project_name . '-' . ($employeeData ? $employeeData->name : '') . '-' . $project->created_at . '.pdf');
            }
            return redirect()->back()->with('error', __('Record not found.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function appendTimesheetTaskHTML(Request $request)
    {
        $project_id     = $request->has('project_id') ? $request->project_id : null;
        $task_id        = $request->has('task_id') ? $request->task_id : null;
        $selected_dates = $request->has('selected_dates') ? $request->selected_dates : null;

        $returnHTML = '';

        $project = Project::find($project_id);

        if ($project) {
            $task = ProjectTask::find($task_id);

            if ($task && $selected_dates) {
                $twoDates = explode(' - ', $selected_dates);

                $first_day   = $twoDates[0];
                $seventh_day = $twoDates[1];

                $period = CarbonPeriod::create($first_day, $seventh_day);

                $returnHTML .= '<tr><td><span class="task-name">' . $task->name . '</span></td>';

                foreach ($period as $key => $dateobj) {
                    $returnHTML .= '<td><span class="task-time" data-ajax-timesheet-popup="true" data-type="create" data-task-id="' . $task->id . '" data-date="' . $dateobj->format('Y-m-d') . '" data-url="' . route('timesheet.create', $project_id) . '">-</span></td>';
                }

                $returnHTML .= '<td><span class="total-task-time">00:00</span></td></tr>';
            }
        }

        return response()->json(
            [
                'success' => true,
                'html' => $returnHTML,
            ]
        );
    }

    public function filterTimesheetTableView(Request $request)
    {
        $sectionTaskArray = [];
        $project = Project::find($request->project_id);
        if (Auth::user() != null) {
            $authuser         = Auth::user();
        } else {
            $authuser         = User::where('id', $project->created_by)->first();
        }
        $week             = $request->week;
        $project_id       = $request->project_id;
        $timesheet_type   = 'task';
        if ($request->has('week') && $request->has('project_id') && $authuser != null) {
            if ($authuser->type == 'client') {
                $project_ids = Project::where('client_id', auth()->user()->id)->pluck('id', 'id')->toArray();
            } else {
                $project_ids = $authuser->projects()->pluck('project_id', 'project_id')->toArray();
            }
            $timesheets  = Timesheet::select('timesheets.*')->join('projects', 'projects.id', '=', 'timesheets.project_id');
            if ($timesheet_type == 'task') {
                $projects_timesheet = $timesheets->join('project_tasks', 'project_tasks.id', '=', 'timesheets.task_id');
            }
            if ($project_id == '0') {
                $projects_timesheet = $timesheets->whereIn('projects.id', $project_ids);
            } else if (in_array($project_id, $project_ids)) {
                $projects_timesheet = $timesheets->where('timesheets.project_id', $project_id);
            }


            $days               = Utility::getFirstSeventhWeekDay($week);
            $first_day          = $days['first_day'];
            $seventh_day        = $days['seventh_day'];
            $onewWeekDate       = $first_day->format('M d') . ' - ' . $seventh_day->format('M d, Y');
            $selectedDate       = $first_day->format('Y-m-d') . ' - ' . $seventh_day->format('Y-m-d');
            $projects_timesheet = $projects_timesheet->whereDate('date', '>=', $first_day->format('Y-m-d'))->whereDate('date', '<=', $seventh_day->format('Y-m-d'));
            if ($project_id == '0') {
                $timesheets = $projects_timesheet->get()->groupBy(
                    [
                        'project_id',
                        'task_id',
                    ]
                )->toArray();
            } else if (in_array($project_id, $project_ids)) {
                $timesheets = $projects_timesheet->get()->groupBy('task_id')->toArray();
            }

            $returnHTML = Project::getProjectAssignedTimesheetHTML($projects_timesheet, $timesheets, $days, $project_id);

            $totalrecords = count($timesheets);
            if ($project_id != '0') {
                $task_ids = array_keys($timesheets);
                $project  = Project::find($project_id);
                $sections = ProjectTask::getAllSectionedTaskList($request, $project, [], $task_ids);
                foreach ($sections as $key => $section) {
                    $taskArray                              = [];
                    $sectionTaskArray[$key]['section_id']   = $section['section_id'];
                    $sectionTaskArray[$key]['section_name'] = $section['section_name'];
                    foreach ($section['sections'] as $taskkey => $task) {
                        $taskArray[$taskkey]['task_id']   = $task['id'];
                        $taskArray[$taskkey]['task_name'] = $task['taskinfo']['task_name'];
                    }
                    $sectionTaskArray[$key]['tasks'] = $taskArray;
                }
            }

            return response()->json(
                [
                    'success' => true,
                    'totalrecords' => $totalrecords,
                    'selectedDate' => $selectedDate,
                    'sectiontasks' => $sectionTaskArray,
                    'onewWeekDate' => $onewWeekDate,
                    'html' => $returnHTML,
                ]
            );
        }
    }

    public function timesheetCreate(Request $request)
    {
        if (auth()->user()->can('create timesheet')) {
            $parseArray = [];

            $authuser      = Auth::user();
            $project_id    = $request->has('project_id') ? $request->project_id : null;
            $task_id       = $request->has('task_id') ? $request->task_id : null;
            $selected_date = $request->has('date') ? $request->date : null;
            $user_id       = $request->has('date') ? $request->user_id : null;

            $created_by = $user_id != null ? $user_id : $authuser->id;

            $projects = $authuser->projects();

            $project_users   = ProjectUser::join('users', 'users.id', 'project_users.user_id')->where('users.type', '!=', 'company')->where('users.type', '!=', 'client')->where('project_users.project_id', $project_id)->pluck('users.name', 'users.id')->toArray();
            if ($project_id) {
                $project        = $projects->where('projects.id', '=', $project_id)->pluck('projects.project_name', 'projects.id')->all();
                if (!empty($project) && count($project) > 0) {

                    $project_id   = key($project);
                    $project_name = $project[$project_id];

                    $task = ProjectTask::where(
                        [
                            'project_id' => $project_id,
                            'id' => $task_id,
                        ]
                    )->pluck('name', 'id')->all();

                    $task_id   = key($task);
                    $task_name = $task[$task_id];

                    $tasktime = Timesheet::where('task_id', $task_id)->where('created_by', $created_by)->pluck('time')->toArray();

                    $totaltasktime = Utility::calculateTimesheetHours($tasktime);

                    $totalhourstimes = explode(':', $totaltasktime);

                    $totaltaskhour   = $totalhourstimes[0];
                    $totaltaskminute = $totalhourstimes[1];

                    $parseArray = [
                        'project_id' => $project_id,
                        'project_name' => $project_name,
                        'task_id' => $task_id,
                        'task_name' => $task_name,
                        'date' => $selected_date,
                        'totaltaskhour' => $totaltaskhour,
                        'totaltaskminute' => $totaltaskminute,
                    ];

                    return view('projects.timesheets.create', compact('parseArray', 'project_users'));
                }
            } else {
                $projects = $projects->get();

                return view('projects.timesheets.create', compact('projects', 'project_id', 'selected_date', 'project_users'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetStore(Request $request)
    {
        if (auth()->user()->can('create timesheet')) {
            $authuser = Auth::user();
            $project  = Project::find($request->project_id);

            if ($project) {

                $request->validate([
                    'date'          => 'required',
                    'day_type'      => 'required|in:full_day,half_day',
                    'half_day_type' => 'required_if:day_type,half_day|in:morning,after_noon',
                ]);

                $time = ($request->day_type == 'half_day') ? '04:00:00' : '08:00:00';

                $timesheet                      = new Timesheet();
                $timesheet->project_id          = $request->project_id;
                $timesheet->task_id             = $request->task_id;
                $timesheet->date                = $request->date;
                $timesheet->day_type            = $request->day_type;
                $timesheet->half_day_type       = ($request->half_day_type) ? $request->half_day_type : null;
                $timesheet->time                = $time;
                $timesheet->description         = $request->description;
                $timesheet->created_by          = $authuser->id;
                $timesheet->save();
                if (auth()->user()->type != 'Employee') {
                    $employeeData               = Employee::where('user_id', $request->user)->first();
                } else {
                    $employeeData               = Employee::where('user_id', auth()->user()->id)->first();
                }
                $clockIn = '09:00:00';
                $clockOut = '18:00:00';
                if (!empty($employeeData)) {
                    if ($timesheet->day_type == 'half_day') {
                        if ($timesheet->half_day_type == 'morning') {
                            $clockIn = '09:00:00';
                            $clockOut = '13:00:00';
                        } else if ($timesheet->half_day_type == 'after_noon') {
                            $clockIn = '02:00:00';
                            $clockOut = '18:00:00';
                        }
                    } else {
                        $clockIn = '09:00:00';
                        $clockOut = '18:00:00';
                    }
                    $startTime          = Utility::getValByName('company_start_time');
                    $endTime            = Utility::getValByName('company_end_time');
                    $totalLateSeconds   = strtotime($clockIn) - strtotime($startTime);
                    $hours = floor($totalLateSeconds / 3600);
                    $mins  = floor($totalLateSeconds / 60 % 60);
                    $secs  = floor($totalLateSeconds % 60);
                    $late  = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                    $totalEarlyLeavingSeconds = strtotime($endTime) - strtotime($clockOut);
                    $hours                    = floor($totalEarlyLeavingSeconds / 3600);
                    $mins                     = floor($totalEarlyLeavingSeconds / 60 % 60);
                    $secs                     = floor($totalEarlyLeavingSeconds % 60);
                    $earlyLeaving             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                    if (strtotime($clockOut) > strtotime($endTime)) {
                        $totalOvertimeSeconds = strtotime($clockOut) - strtotime($endTime);
                        $hours                = floor($totalOvertimeSeconds / 3600);
                        $mins                 = floor($totalOvertimeSeconds / 60 % 60);
                        $secs                 = floor($totalOvertimeSeconds % 60);
                        $overtime             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                    } else {
                        $overtime = '00:00:00';
                    }
                    AttendanceEmployee::create([
                        'employee_id'       => $employeeData->id,
                        'date'              => $request->date,
                        'status'            => 'Present',
                        'late'              => $late,
                        'early_leaving'     => ($earlyLeaving > 0) ? $earlyLeaving : '00:00:00',
                        'overtime'          => $overtime,
                        'clock_in'          => $clockIn,
                        'clock_out'         => $clockOut,
                        'created_by'        => auth()->user()->id,
                    ]);
                }
                return redirect()->back()->with('success', __('Timesheet Created Successfully!'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetEdit(Request $request, $project_id, $timesheet_id)
    {
        if (auth()->user()->can('edit timesheet')) {
            $authuser = Auth::user();

            $task_id    = $request->has('task_id') ? $request->task_id : null;
            $user_id    = $request->has('date') ? $request->user_id : null;
            $created_by = $user_id != null ? $user_id : $authuser->id;

            $project_view = '';

            if ($request->has('project_view')) {
                $project_view = $request->project_view;
            }

            $projects = $authuser->projects();

            $timesheet = Timesheet::find($timesheet_id);

            if ($timesheet) {

                $project = $projects->where('projects.id', '=', $project_id)->pluck('projects.project_name', 'projects.id')->all();

                if (!empty($project) && count($project) > 0) {

                    $project_id   = key($project);
                    $project_name = $project[$project_id];
                    $project_users   = ProjectUser::join('users', 'users.id', 'project_users.user_id')->where('users.type', '!=', 'company')->where('users.type', '!=', 'client')->where('project_users.project_id', $project_id)->pluck('users.name', 'users.id')->toArray();

                    $task = ProjectTask::where(
                        [
                            'project_id' => $project_id,
                            'id' => $task_id,
                        ]
                    )->pluck('name', 'id')->all();

                    $task_id   = key($task);
                    $task_name = $task[$task_id];

                    $tasktime = Timesheet::where('task_id', $task_id)->where('created_by', $created_by)->pluck('time')->toArray();

                    $totaltasktime = Utility::calculateTimesheetHours($tasktime);

                    $totalhourstimes = explode(':', $totaltasktime);

                    $totaltaskhour   = $totalhourstimes[0];
                    $totaltaskminute = $totalhourstimes[1];

                    $time = explode(':', $timesheet->time);

                    $parseArray = [
                        'project_id' => $project_id,
                        'project_name' => $project_name,
                        'task_id' => $task_id,
                        'task_name' => $task_name,
                        'time_hour' => $time[0] < 10 ? $time[0] : $time[0],
                        'time_minute' => $time[1] < 10 ? $time[1] : $time[1],
                        'totaltaskhour' => $totaltaskhour,
                        'totaltaskminute' => $totaltaskminute,
                    ];

                    return view('projects.timesheets.edit', compact('timesheet', 'parseArray', 'project_users'));
                }
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetUpdate(Request $request, $timesheet_id)
    {
        if (auth()->user()->can('edit timesheet')) {
            $project = Project::find($request->project_id);

            if ($project) {

                $request->validate([
                    'date'          => 'required',
                    'day_type'      => 'required|in:full_day,half_day',
                    'half_day_type' => 'required_if:day_type,half_day|in:morning,after_noon',
                ]);

                $time = ($request->day_type == 'half_day') ? '04:00:00' : '08:00:00';

                $timesheet                      = Timesheet::find($timesheet_id);
                $timesheet->project_id          = $request->project_id;
                $timesheet->task_id             = $request->task_id;
                $timesheet->date                = $request->date;
                $timesheet->day_type            = $request->day_type;
                $timesheet->half_day_type       = ($request->half_day_type) ? $request->half_day_type : null;
                $timesheet->time                = $time;
                $timesheet->description         = $request->description;
                $timesheet->save();
                if (auth()->user()->type != 'Employee') {
                    $employeeData               = Employee::where('user_id', $request->user)->first();
                } else {
                    $employeeData               = Employee::where('user_id', auth()->user()->id)->first();
                }
                $clockIn = '09:00:00';
                $clockOut = '18:00:00';
                if (!empty($employeeData)) {
                    if ($timesheet->day_type == 'half_day') {
                        if ($timesheet->half_day_type == 'morning') {
                            $clockIn = '09:00:00';
                            $clockOut = '13:00:00';
                        } else if ($timesheet->half_day_type == 'after_noon') {
                            $clockIn = '02:00:00';
                            $clockOut = '18:00:00';
                        }
                    } else {
                        $clockIn = '09:00:00';
                        $clockOut = '18:00:00';
                    }
                    $startTime          = Utility::getValByName('company_start_time');
                    $endTime            = Utility::getValByName('company_end_time');
                    $totalLateSeconds   = strtotime($clockIn) - strtotime($startTime);

                    $hours = floor($totalLateSeconds / 3600);
                    $mins  = floor($totalLateSeconds / 60 % 60);
                    $secs  = floor($totalLateSeconds % 60);
                    $late  = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                    $totalEarlyLeavingSeconds = strtotime($endTime) - strtotime($clockOut);
                    $hours                    = floor($totalEarlyLeavingSeconds / 3600);
                    $mins                     = floor($totalEarlyLeavingSeconds / 60 % 60);
                    $secs                     = floor($totalEarlyLeavingSeconds % 60);
                    $earlyLeaving             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                    if (strtotime($clockOut) > strtotime($endTime)) {
                        $totalOvertimeSeconds = strtotime($clockOut) - strtotime($endTime);
                        $hours                = floor($totalOvertimeSeconds / 3600);
                        $mins                 = floor($totalOvertimeSeconds / 60 % 60);
                        $secs                 = floor($totalOvertimeSeconds % 60);
                        $overtime             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                    } else {
                        $overtime = '00:00:00';
                    }
                    $check = AttendanceEmployee::where('employee_id', $employeeData->id)->where('date', $request->date)->first();
                    if ($check) {
                        $check->update([
                            'employee_id'       => $employeeData->id,
                            'date'              => $request->date,
                            'status'            => 'Present',
                            'late'              => $late,
                            'early_leaving'     => ($earlyLeaving > 0) ? $earlyLeaving : '00:00:00',
                            'overtime'          => $overtime,
                            'clock_in'          => $clockIn,
                            'clock_out'         => $clockOut,
                            'created_by'        => auth()->user()->id,
                        ]);
                    }
                }
                return redirect()->back()->with('success', __('Timesheet Updated Successfully!'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetDestroy($timesheet_id)
    {
        if (auth()->user()->can('delete timesheet')) {
            $timesheet = Timesheet::find($timesheet_id);
            if ($timesheet) {
                $timesheet->delete();
            }
            return redirect()->back()->with('success', __('Timesheet deleted Successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function timesheetList()
    {
        return view('projects.timesheet_list');
    }

    public function timesheetListGet(Request $request)
    {
        $authuser = Auth::user();
        $week     = $request->week;

        if ($request->has('week') && $request->has('project_id')) {
            $project_id = $request->project_id;

            $project_ids        = $authuser->projects()->pluck('project_id')->toArray();
            $timesheets         = Timesheet::select('timesheets.*')->join('projects', 'projects.id', '=', 'timesheets.project_id');
            $projects_timesheet = $timesheets->join('project_tasks', 'project_tasks.id', '=', 'timesheets.task_id');

            if ($project_id == '0') {
                $projects_timesheet = $timesheets->whereIn('projects.id', $project_ids);
            } else if (in_array($project_id, $project_ids)) {
                $projects_timesheet = $timesheets->where('timesheets.project_id', $project_id);
            }

            $days        = Utility::getFirstSeventhWeekDay($week);
            $first_day   = $days['first_day'];
            $seventh_day = $days['seventh_day'];

            $onewWeekDate = $first_day->format('M d') . ' - ' . $seventh_day->format('M d, Y');
            $selectedDate = $first_day->format('Y-m-d') . ' - ' . $seventh_day->format('Y-m-d');

            $projects_timesheet = $projects_timesheet->whereDate('date', '>=', $first_day->format('Y-m-d'))->whereDate('date', '<=', $seventh_day->format('Y-m-d'));

            if ($project_id == '0') {
                $timesheets = $projects_timesheet->get()->groupBy(
                    [
                        'project_id',
                        'task_id',
                    ]
                )->toArray();
            } else if (in_array($project_id, $project_ids)) {
                $timesheets = $projects_timesheet->get()->groupBy('task_id')->toArray();
            }

            $returnHTML = Project::getProjectAssignedTimesheetHTML($projects_timesheet, $timesheets, $days, $project_id);

            $totalrecords = count($timesheets);

            if ($project_id != '0') {
                $task_ids = array_keys($timesheets);
                $project  = Project::find($project_id);
                $sections = ProjectTask::getAllSectionedTaskList($request, $project, [], $task_ids);

                foreach ($sections as $key => $section) {
                    $taskArray = [];

                    $sectionTaskArray[$key]['section_id']   = $section['section_id'];
                    $sectionTaskArray[$key]['section_name'] = $section['section_name'];

                    foreach ($section['sections'] as $taskkey => $task) {
                        $taskArray[$taskkey]['task_id']   = $task['id'];
                        $taskArray[$taskkey]['task_name'] = $task['taskinfo']['task_name'];
                    }
                    $sectionTaskArray[$key]['tasks'] = $taskArray;
                }
            }

            return response()->json(
                [
                    'success' => true,
                    'totalrecords' => $totalrecords,
                    'selectedDate' => $selectedDate,
                    'sectiontasks' => $sectionTaskArray,
                    'onewWeekDate' => $onewWeekDate,
                    'html' => $returnHTML,
                ]
            );
        }
    }
}
