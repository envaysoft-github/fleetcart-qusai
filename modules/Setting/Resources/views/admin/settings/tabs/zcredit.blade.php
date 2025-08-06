<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('zcredit_enabled', trans('setting::attributes.zcredit_enabled'), trans('setting::settings.form.enable_zcredit'), $errors, $settings) }}
        {{ Form::text('translatable[zcredit_label]', trans('setting::attributes.translatable.zcredit_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[zcredit_description]', trans('setting::attributes.translatable.zcredit_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}
        {{ Form::textarea('zcredit_key', trans('setting::attributes.zcredit_key'), $errors, $settings, ['rows' => 3, 'required' => true]) }}
    </div>
</div>
