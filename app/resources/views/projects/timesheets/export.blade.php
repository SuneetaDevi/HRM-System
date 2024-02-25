{{ Form::open(['url' => route('timesheet.export.store', $project->id), 'id' => 'project_form']) }}
<div class="modal-body">
    <div class="form-group">
        {{ Form::label('date', __('Date Range'), ['class' => 'form-label']) }}
        {{ Form::text('date', null, ['class' => 'form-control flatpickr-date-range', 'required' => 'required']) }}
    </div>
    <div class="form-group">
        {{ Form::label('user', __('User'), ['class' => 'form-label']) }}
        {{ Form::select('user', $projectEmployee, null, ['class' => 'form-control select', 'required' => 'required']) }}
    </div>
</div>
<div class="modal-footer">
    <input type="submit" value="{{ __('Export') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
