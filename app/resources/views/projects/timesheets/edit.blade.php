{{ Form::model($timesheet, ['route' => ['timesheet.update', $timesheet->id], 'method' => 'PUT']) }}
<div class="modal-body">


    <input type="hidden" name="project_id" value="{{ $parseArray['project_id'] }}">
    <input type="hidden" name="task_id" value="{{ $parseArray['task_id'] }}">
    <input type="hidden" name="date" value="{{ $timesheet->date }}">
    <input type="hidden" id="totaltasktime"
        value="{{ $parseArray['totaltaskhour'] . ':' . $parseArray['totaltaskminute'] }}">

    <div class="text-center details form-group">
        <label for="descriptions"
            class="form-label">{{ $parseArray['project_name'] . ' : ' . $parseArray['task_name'] }}</label>
    </div>


    <div class="row">
        @if (auth()->user()->type != 'Employee')
            <div class="form-group">
                {{ Form::label('user', __('User'), ['class' => 'form-label']) }}
                {{ Form::select('user', $project_users, null, ['class' => 'form-control select', 'required' => 'required']) }}
            </div>
        @endif
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">{{ __('Day Type') }}</label>
                <div class="form-check">
                    {{ Form::radio('day_type', 'full_day', $timesheet->day_type == 'full_day' ? true : false, ['class' => 'form-check-input', 'id' => 'day_type_full_day']) }}
                    {{ Form::label('day_type_full_day', __('Full Day'), ['class' => 'form-check-label']) }}
                </div>
                <div class="form-check">
                    {{ Form::radio('day_type', 'half_day', $timesheet->day_type == 'half_day' ? true : false, ['class' => 'form-check-input', 'id' => 'day_type_half_day']) }}
                    {{ Form::label('day_type_half_day', __('Half Day'), ['class' => 'form-check-label']) }}
                </div>
            </div>
        </div>
    </div>

    <div class="form-group half_day_type">
        <label class="form-label">{{ __('Half Day Type') }}</label>
        <div class="form-check">
            {{ Form::radio('half_day_type', 'morning', $timesheet->half_day_type == 'morning' ? true : false, ['class' => 'form-check-input', 'id' => 'half_day_type_full_day']) }}
            {{ Form::label('half_day_type_full_day', __('Morning'), ['class' => 'form-check-label']) }}
        </div>
        <div class="form-check">
            {{ Form::radio('half_day_type', 'after_noon', $timesheet->half_day_type == 'after_noon' ? true : false, ['class' => 'form-check-input', 'id' => 'half_day_type_half_day']) }}
            {{ Form::label('half_day_type_half_day', __('After Noon'), ['class' => 'form-check-label']) }}
        </div>
    </div>

    <div class="form-group">
        <label for="description">{{ __('Description') }}</label>
        <textarea class="form-control form-control-light" id="description" rows="3" name="description">{{ $timesheet->description }}</textarea>
    </div>

    <div class="col-md-12">
        <div class="display-total-time">
            <i class="ti ti-clock"></i>
            <span>{{ __('Total Time') }} :
                {{ $parseArray['totaltaskhour'] . ' ' . __('Hours') . ' ' . $parseArray['totaltaskminute'] . ' ' . __('Minutes') }}</span>

        </div>
    </div>

</div>

<div class="modal-footer">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
