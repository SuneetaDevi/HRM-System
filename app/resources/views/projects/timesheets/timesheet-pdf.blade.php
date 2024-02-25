@php
    $logo = \App\Models\Utility::get_file('uploads/logo/');
    $companyLogo = Utility::getValByName('company_logo_dark');
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $project->project_name }}</title>
    <style>
        /* Define your CSS styles here */
        body {
            font-family: Arial, sans-serif;
        }

        table,
        .table {
            width: 100%;
            page-break-inside: avoid;
            border-collapse: collapse;
            border: 0.5px solid #000;
            margin-bottom: 8px;
        }

        .border-head {
            border: 1px solid #000;
        }

        th,
        td {
            text-align: left;
            padding-left: 8px;
            padding-right: 8px;
        }

        .overview th,
        .overview td {
            text-align: left;
        }

        .overview {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div style="text-align: center;">
        <img src="{{ $logo . '/' . (isset($companyLogo) && !empty($companyLogo) ? $companyLogo : 'logo-dark.png') }}"
            alt="{{ config('app.name', 'ERPGo') }}" style="width: 100px;" />
    </div>
    <h2 style="text-align: center;">TIMESHEET</h2>
    <table style="width: 48%; float: left; margin-bottom: 8px; border-collapse: collapse; border: 0.5px solid #000;">
        <tr>
            <td style="border: 1px solid #000; text-align: center; font-size: 13px;">Name</td>
            <td style="border: 1px solid #000; text-align: center; font-size: 13px;">
                <h3>{{ $employeeData->name }}</h3>
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; text-align: center; font-size: 13px;">Manager</td>
            <td style="border: 1px solid #000; text-align: center; font-size: 13px;">
                <h3>{{ $manager->name }}</h3>
            </td>
        </tr>
    </table>

    <!-- Spacer column -->
    <div style="width: 4%; float: left;"></div>

    <!-- Second Table: Duration -->
    <table style="width: 48%; float: left; margin-bottom: 8px; border-collapse: collapse; border: 0.5px solid #000;">
        <tr>
            <td colspan="4" style="font-size: 13px; border: 1px solid #000; text-align: center; padding: 17px;">
                Duration
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; text-align: center; width: 10%; padding: 15.011px;font-size: 13px;">
                From
            </td>
            <td style="border: 1px solid #000; text-align: center; width: 35%; padding: 15.011px;font-size: 13px;">
                {{ $firstDay->format('d,m,Y') }}
            </td>
            <td style="border: 1px solid #000; text-align: center; width: 10%; padding: 15.011px;font-size: 13px;">
                To
            </td>
            <td style="border: 1px solid #000; text-align: center; width: 35%; padding: 15.011px;font-size: 13px;">
                {{ $lastDay->format('d,m,Y') }}
            </td>
        </tr>
    </table>
    <div style="clear: both;"></div>
    <table class="overview" style="width: 100%;">
        <thead>
            <th style="width: 10%;" class="border-head">Serial No.</th>
            <th style="width: 10%;" class="border-head">Date</th>
            <th style="width: 10%;" class="border-head">Day</th>
            <th style="width: 60%;" class="border-head">Description of Services</th>
            <th style="width: 10%;" class="border-head">Working Day</th>
        </thead>
        <tbody>
            @php
                $totalWorkingDays = 0;
            @endphp
            @foreach ($dateArrays as $dateArrayKey => $dateArray)
                @php
                    $found = false;
                @endphp
                @foreach ($timesheets as $timesheet)
                    @if ($timesheet->date == $dateArray->toDateString())
                        <tr>
                            <td class="border-head">{{ $dateArrayKey + 1 }}</td>
                            <td class="border-head">{{ Carbon\Carbon::parse($timesheet->date)->format('d,M,y') }}</td>
                            <td class="border-head">{{ Carbon\Carbon::parse($timesheet->date)->format('D') }}</td>
                            <!-- <td class="border-head">{{ $timesheet->on_leave ? 'On Leave' : $timesheet->reason }} -->
                            <td class="border-head">{{ $leave->leave_reason }}
                            </td>
           
                            <td class="border-head">
                                @if (!$timesheet->on_leave)
                                    @if ($timesheet->day_type == 'full_day')
                                        {{ '1' }}
                                        @php
                                            $totalWorkingDays += 1;
                                        @endphp
                                    @elseif($timesheet->day_type == 'half_day')
                                        {{ '0.5' }}
                                        @php
                                            $totalWorkingDays += 0.5;
                                        @endphp
                                    @endif
                                @else
                                    {{ '0' }}
                                    @php
                                        $totalWorkingDays += 0;
                                    @endphp
                                @endif
                            </td>
                        </tr>
                        @php
                            $found = true;
                            break;
                        @endphp
                    @endif
                @endforeach
                @if (!$found)
                    <tr>
                        <td class="border-head">{{ $dateArrayKey + 1 }}</td>
                        <td class="border-head">{{ Carbon\Carbon::parse($dateArray)->format('d,M,y') }}</td>
                        <td class="border-head">{{ Carbon\Carbon::parse($dateArray)->format('D') }}</td>
                        <td class="border-head">{{ '0' }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    <table class="overview" style="width: 100%;">
        <tr>
            <td class="border-head"><strong>Total Working Days:</strong></td>
            <td style="width: 11%;" class="border-head">{{ $totalWorkingDays }}</td>
        </tr>
    </table>
    <small style="margin-bottom: 10px;"><b>Attachment :</b> <span style="font-weight: normal;">
            Screenshot of Approved Timesheets only for above duration in one combined pdf file.</span></small>
</body>

</html>
