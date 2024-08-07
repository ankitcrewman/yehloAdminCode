@extends('layouts.admin.app')

@section('title', translate('Update Service Man'))


@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title text-break">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('messages.update_servicemen') }}</span>
            </h1>
        </div>
        <!-- End Page Header -->
        <form action="{{ route('admin.service.update', [$servicemen['id']]) }}" method="post" class="js-validate"
            enctype="multipart/form-data">
            @csrf
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-title-icon"><i class="tio-user"></i></span>
                        <span>
                            {{ translate('general_information') }}
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.first_name') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="text" value="{{ $servicemen['first_name'] }}" name="f_name"
                                            class="form-control" placeholder="{{ translate('messages.first_name') }}"
                                            required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.last_name') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="text" value="{{ $servicemen['last_name'] }}" name="l_name"
                                            class="form-control" placeholder="{{ translate('messages.last_name') }}"
                                            required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.email') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="email" value="{{ $servicemen['email'] }}" name="email"
                                            class="form-control"
                                            placeholder="{{ translate('messages.Ex:') }} ex@example.com" required>
                                    </div>
                                </div>
                                <div class="col-sm-6 ">

                                    <div class="form-group mb-0">
                                        <label class="input-label" for="store_id">{{ translate('messages.store') }} <span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span><span class="input-label-secondary"></span></label>
                                        @php
                                            $store = \App\Models\Store::where('id', $servicemen['store_id'])->first();
                                        @endphp

                                        <select name="store_id" id="store_id" disabled
                                            data-placeholder="{{ translate('messages.select_store') }}"
                                            class="js-data-example-ajax form-control"
                                            oninvalid="this.setCustomValidity('{{ translate('messages.please_select_store') }}')">


                                            <option value="{{ $store->id }}"
                                                {{ $store->id == $servicemen['store_id'] ? 'selected' : '' }}>
                                                {{ $store->name }}</option>

                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex flex-column h-100">
                                <label>{{ translate('messages.servicemen_image') }} <small class="text-danger">* (
                                        {{ translate('messages.ratio') }} 1:1 )</small></label>
                                <div class="text-center py-3 my-auto">
                                    <img class="img--100 rounded onerror-image" id="viewer"
                                        src="{{ \App\CentralLogics\Helpers::onerror_image_helper($servicemen['serviceman_image'], asset('storage/app/public/service-man/') . '/' . $servicemen['image'], asset('public/assets/admin/img/admin.png'), 'delivery-man/') }}"
                                        data-onerror-image="{{ asset('/public/assets/admin/img/admin.png') }}"
                                        alt="delivery-man image" />
                                </div>
                                <div class="custom-file">
                                    <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                        accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                    <label class="custom-file-label"
                                        for="customFileEg1">{{ translate('messages.choose_file') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.identity_type') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <select name="identity_type" class="form-control  js-select2-custom">
                                            <option value="passport"
                                                {{ $servicemen['identity_type'] == 'passport' ? 'selected' : '' }}>
                                                {{ translate('messages.passport') }}
                                            </option>
                                            <option value="driving_license"
                                                {{ $servicemen['identity_type'] == 'driving_license' ? 'selected' : '' }}>
                                                {{ translate('messages.driving_license') }}
                                            </option>
                                            <option value="nid"
                                                {{ $servicemen['identity_type'] == 'nid' ? 'selected' : '' }}>
                                                {{ translate('messages.nid') }}
                                            </option>
                                            <option value="store_id"
                                                {{ $servicemen['identity_type'] == 'store_id' ? 'selected' : '' }}>
                                                {{ translate('messages.store_id') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.identity_number') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="text" name="identity_number"
                                            value="{{ $servicemen['identity_number'] }}" class="form-control"
                                            placeholder="{{ translate('messages.Ex:') }} DH-23434-LS" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6 pb-0">
                                    <div class="row g-2">
                                        <div class="col-12 pb-0">
                                            <div class="form-group mb-0">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('messages.identity_images') }}
                                            </div>
                                        </div>

                                        @if ($servicemen['identification_image'] != null)
                                            @foreach ( json_decode($servicemen['identification_image'] , true)  as $img)
                                                <div class="col-6 spartan_item_wrapper size--sm">
                                                    <img class="rounded border"
                                                        src="{{ \App\CentralLogics\Helpers::onerror_image_helper($img, asset('storage/app/public') . '/' . $img, asset('public/assets/admin/img/160x160/img1.jpg'), 'service-man-identification') }}">
                                                </div>
                                            @endforeach
                                        @endif

                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('messages.update_identity_image') }}</label>
                                    <div>
                                        <div class="row g-2 mt-0" id="coba"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <i class="tio-user"></i>
                        </span>
                        <span>
                            {{ translate('messages.account_information') }}
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="form-group mb-0">
                                <label class="input-label"
                                    for="exampleFormControlInput1">{{ translate('messages.phone') }}<span
                                        class="form-label-secondary text-danger" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('messages.Required.') }}"> *
                                    </span>
                                </label>
                                <input type="tel" id="phone" name="phone"
                                    value="{{ $servicemen['phone_number'] }}" class="form-control"
                                    placeholder="{{ translate('messages.Ex:') }} 017********" required>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="js-form-message form-group mb-0">
                                <label class="input-label" for="signupSrPassword">{{ translate('messages.password') }}
                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"><img
                                            src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                            alt="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"></span>
                                </label>

                                <div class="input-group input-group-merge">
                                    <input type="password" class="js-toggle-password form-control" name="password"
                                        id="signupSrPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                        title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"
                                        placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                        aria-label="8+ characters required"
                                        data-msg="Your password is invalid. Please try again."
                                        data-hs-toggle-password-options='{
                                    "target": [".js-toggle-password-target-1"],
                                    "defaultClass": "tio-hidden-outlined",
                                    "showClass": "tio-visible-outlined",
                                    "classChangeTarget": ".js-toggle-passowrd-show-icon-1"
                                    }'>
                                    <div class="js-toggle-password-target-1 input-group-append">
                                        <a class="input-group-text" href="javascript:;">
                                            <i class="js-toggle-passowrd-show-icon-1 tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="js-form-message form-group mb-0">
                                <label class="input-label"
                                    for="signupSrConfirmPassword">{{ translate('messages.confirm_password') }}</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" class="js-toggle-password form-control" name="confirmPassword"
                                        id="signupSrConfirmPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                        title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"
                                        placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                        aria-label="8+ characters required"
                                        data-msg="Password does not match the confirm password."
                                        data-hs-toggle-password-options='{
                                        "target": [".js-toggle-password-target-2"],
                                        "defaultClass": "tio-hidden-outlined",
                                        "showClass": "tio-visible-outlined",
                                        "classChangeTarget": ".js-toggle-passowrd-show-icon-2"
                                        }'>
                                    <div class="js-toggle-password-target-2 input-group-append">
                                        <a class="input-group-text" href="javascript:;">
                                            <i class="js-toggle-passowrd-show-icon-2 tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container justify-content-end mt-3">
                <button type="reset" id="reset_btn" class="btn btn--reset">{{ translate('messages.reset') }}</button>

                {{--                @if ($servicemen->application_status == 'pending' || $servicemen->application_status == 'denied') --}}
                {{--                    <button type="submit" class="btn btn--primary">{{translate('messages.Edit_&_Approve')}}</button> --}}
                {{--                @else --}}
                <button type="submit" class="btn btn--primary">{{ translate('messages.submit') }}</button>
                {{--                @endif --}}
            </div>
        </form>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script>
        "use strict";

        function readURL(input) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();

                reader.onload = function(e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function() {
            readURL(this);
        });


        $(function() {
            $("#coba").spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '100px',
                groupClassName: 'col-6 spartan_item_wrapper size--sm',
                maxFileSize: '',
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/400x400/img2.jpg') }}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function(index, file) {

                },
                onRenderedPreview: function(index) {

                },
                onRemoveRow: function(index) {

                },
                onExtensionErr: function(index, file) {
                    toastr.error('Please only input png or jpg type file', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function(index, file) {
                    toastr.error('File size too big', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });

        $('#reset_btn').click(function() {
            $('#viewer').attr('src', '{{ asset('storage/app/public/service-man') }}/{{ $servicemen['image'] }}');
            $("#coba").empty().spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '120px',
                groupClassName: 'col-6 spartan_item_wrapper size--sm',
                maxFileSize: '',
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/400x400/img2.jpg') }}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function(index, file) {

                },
                onRenderedPreview: function(index) {

                },
                onRemoveRow: function(index) {

                },
                onExtensionErr: function(index, file) {
                    toastr.error(
                        '{{ translate('messages.please_only_input_png_or_jpg_type_file') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                },
                onSizeErr: function(index, file) {
                    toastr.error('{{ translate('messages.file_size_too_big') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        })


        // $('#store_id').select2({
        //     ajax: {
        //         url: '{{ url('/') }}/admin/store/get-stores',
        //         data: function(params) {
        //             return {
        //                 q: params.term, // search term
        //                 page: params.page,
        //                 module_id: {{ Config::get('module.current_module_id') }},
        //             };
        //         },
        //         processResults: function(data) {
        //             return {
        //                 results: data
        //             };
        //         },
        //         __port: function(params, success, failure) {
        //             let $request = $.ajax(params);

        //             $request.then(success);
        //             $request.fail(failure);

        //             return $request;
        //         }
        //     }
        // });
    </script>
@endpush
