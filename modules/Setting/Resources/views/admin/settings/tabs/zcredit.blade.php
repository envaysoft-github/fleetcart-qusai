<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('zcredit_enabled', trans('setting::attributes.zcredit_enabled'), trans('setting::settings.form.enable_zcredit'), $errors, $settings) }}
        {{ Form::text('translatable[zcredit_label]', trans('setting::attributes.translatable.zcredit_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[zcredit_description]', trans('setting::attributes.translatable.zcredit_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}
        {{ Form::textarea('zcredit_key', trans('setting::attributes.zcredit_key'), $errors, $settings, ['rows' => 3, 'required' => true]) }}
        {{ Form::select('zcredit_holderid', trans('setting::attributes.zcredit_holderid'), $errors, $options, $settings, ['required' => true]) }}
        {{ Form::select('zcredit_holder_phone', trans('setting::attributes.zcredit_holder_phone'), $errors, $options, $settings, ['required' => true]) }}
        {{ Form::select('zcredit_holder_email', trans('setting::attributes.zcredit_holder_email'), $errors, $options, $settings, ['required' => true]) }}
        {{ Form::select('zcredit_installment_option', trans('setting::attributes.zcredit_installment_option'), $errors, $installment_options, $settings, ['required' => true]) }}
        {{ Form::number(
            'zcredit_installment_min',
            trans('setting::attributes.zcredit_installment_min'),
            $errors,
            $settings,
            [
                'required' => true,
                'min' => 1,
                'max' => 12,
                'step' => 1,
                'value' => old('zcredit_installment_min', 0)
            ]
        ) }}

        {{ Form::number(
    'zcredit_installment_max',
    trans('setting::attributes.zcredit_installment_max'),
    $errors,
    $settings,
    [
        'required' => true,
        'min' => 1,
        'max' => 12,
        'step' => 1,
        'value' => old('zcredit_installment_max', 0)
    ]
) }}

    </div>
</div>
