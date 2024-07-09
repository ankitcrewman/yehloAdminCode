<div id="headerMain" class="d-none">
    <header id="header"
            class="navbar navbar-expand-lg navbar-fixed navbar-height navbar-flush navbar-container navbar-bordered pr-0">
        <div class="navbar-nav-wrap">

            <div class="navbar-nav-wrap-content-left d-xl-none">
                <!-- Navbar Vertical Toggle -->
                <button type="button" class="js-navbar-vertical-aside-toggle-invoker close mr-3">
                    <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                       data-placement="right" title="Collapse"></i>
                    <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                       data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
                       data-toggle="tooltip" data-placement="right" title="Expand"></i>
                </button>
                <!-- End Navbar Vertical Toggle -->
            </div>

            <!-- Secondary Content -->
            <div class="navbar-nav-wrap-content-right flex-grow-1 w-0">
                <!-- Navbar -->
                <ul class="navbar-nav align-items-center flex-row flex-grow-1 __navbar-nav">

                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.users.dashboard')}}" id="tourb-6" class="__nav-link {{ Request::is('admin/users*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/user.svg')}}" alt="public/img">
                            <span>{{ translate('Users')}}</span>
                        </a>
                    </li>

                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.transactions.store.withdraw_list')}}" id="tourb-7" class="__nav-link {{ Request::is('admin/transactions*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/transaction-and-report.svg')}}" alt="public/img">
                            <span>{{ translate('Transactions & Reports')}}</span>
                        </a>
                    </li>

                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.business-settings.business-setup') }}" id="tourb-3" class="__nav-link {{ Request::is('admin/business-settings*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/setting-icon.svg')}}" alt="public/img">
                            <span>{{ translate('messages.Settings') }}</span>
                            <svg width="14" viewBox="0 0 14 14" fill="none">
                                <path d="M2.33325 5.25L6.99992 9.91667L11.6666 5.25" stroke="#006161" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <div class="__nav-module" id="tourb-4">
                            <div class="__nav-module-header">
                                <div class="inner">
                                    <h4>{{translate('Settings')}}</h4>
                                    <p>
                                        {{translate('Monitor your business general settings from here')}}
                                    </p>
                                </div>
                            </div>
                            <div class="__nav-module-body">
                                <ul>
                                    @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                                    <li>
                                        <a href="{{ route('admin.business-settings.module.index') }}" class="next-tour">
                                            <img src="{{asset('/public/assets/admin/img/navbar-setting-icon/module.svg')}}" alt="">
                                            <span>{{translate('System Module Setup')}}</span>
                                        </a>
                                    </li>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::module_permission_check('zone'))
                                    <li>
                                        <a href="{{ route('admin.business-settings.zone.home') }}" class="next-tour">
                                            <img src="{{asset('/public/assets/admin/img/navbar-setting-icon/location.svg')}}" alt="">
                                            <span>{{translate('Zone Setup')}}</span>
                                        </a>
                                    </li>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                                    <li>
                                        <a href="{{ route('admin.business-settings.business-setup') }}" class="next-tour">
                                            <img src="{{asset('/public/assets/admin/img/navbar-setting-icon/business.svg')}}" alt="">
                                            <span>{{translate('Business Settings')}}</span>
                                        </a>
                                    </li>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                                    <li>
                                        <a href="{{ route('admin.business-settings.third-party.payment-method') }}" class="next-tour">
                                            <img src="{{asset('/public/assets/admin/img/navbar-setting-icon/third-party.svg')}}" alt="">
                                            <span>{{translate('3rd Party')}}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{route('admin.business-settings.social-media.index')}}" class="next-tour">
                                            <img src="{{asset('/public/assets/admin/img/navbar-setting-icon/social.svg')}}" alt="">
                                            <span>{{translate('Social Media and Page Setup')}}</span>
                                        </a>
                                    </li>
                                    @endif
                                </ul>
                                <div class="text-center mt-2">
                                    <a href="{{ route('admin.business-settings.business-setup') }}" class="next-tour">{{translate('View All')}}</a>
                                </div>
                            </div>
                        </div>
                    </li>
                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.dispatch.dashboard')}}" id="tourb-8" class="__nav-link {{ Request::is('admin/dispatch*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/dispatch.svg')}}" alt="public/img">
                            <span>{{ translate('Dispatch Management')}}</span>
                        </a>
                    </li>
                    @endif

                    <li class="nav-item max-sm-m-0 ml-auto mr-lg-3">
                        <a class="btn btn-icon rounded-circle nav-msg-icon"
                            href="{{route('admin.message.list')}}">
                            <img src="{{asset('/public/assets/admin/img/new-img/message-icon.svg')}}" alt="public/img">
                            @php($message=\App\Models\Conversation::whereUserType('admin')->where('unread_message_count','>','0')->count())
                            @if($message!=0)
                                <span class="btn-status btn-status-danger">{{ $message }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item max-sm-m-0">
                        <div class="hs-unfold">
                            <div>
                                @php( $local = session()->has('local')?session('local'): null)
                                @php($lang = \App\Models\BusinessSetting::where('key', 'system_language')->first())
                                @if ($lang)
                                <div
                                    class="topbar-text dropdown disable-autohide text-capitalize d-flex">
                                    <a class="topbar-link dropdown-toggle d-flex align-items-center title-color"
                                    href="#" data-toggle="dropdown">
                                    @foreach(json_decode($lang['value'],true) as $data)
                                            @if($data['code']==$local)
                                            <i class="tio-globe"></i> {{$data['code']}}

                                            @elseif(!$local &&  $data['default'] == true)
                                            <i class="tio-globe"></i> {{$data['code']}}
                                            @endif
                                        @endforeach
                                    </a>
                                    <ul class="dropdown-menu lang-menu">
                                        @foreach(json_decode($lang['value'],true) as $key =>$data)
                                            @if($data['status']==1)
                                                <li>
                                                    <a class="dropdown-item py-1"
                                                       href="{{route('admin.lang',[$data['code']])}}">
                                                        <span class="text-capitalize">{{$data['code']}}</span>
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            </div>
                        </div>
                    </li>
                    @php($mod = \App\Models\Module::find(Config::get('module.current_module_id')))
                    <li class="nav-item __nav-item">
                        <a href="javascript:void(0)" class="__nav-link module--nav-icon" id="tourb-0">
                            @if ($mod->module_type != 'services')
                            <img  src="{{\App\CentralLogics\Helpers::onerror_image_helper($mod->icon, asset('storage/app/public/module/').'/' . $mod->icon, asset('/public/assets/admin/img/new-img/module-icon.svg') ,'module/')}}"
                                    class="onerror-image"
                                  data-onerror-image="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}"
                           width="20px" alt="public/img">
                            @else
                            <img src="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}" alt="public/img">
                            @endif
                            <span class="text-white">{{ $mod->module_type != 'services' ? $mod->module_name : translate('modules') }}</span>
                            <img  src="{{asset('/public/assets/admin/img/new-img/angle-white.svg')}}" class="d-none d-lg-block ml-xl-2" alt="public/img">
                        </a>
                        <div class="__nav-module style-2" id="tourb-1">
                            @php($modules = \App\Models\Module::when(auth('admin')->user()->zone_id, function($query){
                                $query->whereHas('zones',function($query){
                                    $query->where('zone_id',auth('admin')->user()->zone_id);
                                });
                            })->where('module_type', '!=', 'services')->Active()->get())
                            @if(isset($modules) && ($modules->count()>0))
                            <div class="__nav-module-header">
                                <div class="inner">
                                    <h4>{{translate('Modules Section')}}</h4>
                                    <p>
                                        {{translate('Select Module & Monitor your business module wise')}}
                                    </p>
                                </div>
                            </div>
                            <div class="__nav-module-body">
                                <div class="__nav-module-items">
                                    @foreach ($modules as $module)
                                        <a href="javascript:"

                                            data-module-id="{{ $module->id }}"
                                            data-url="{{route('admin.dashboard')}}"
                                            data-filter="module_id"

                                        class="__nav-module-item set-module {{Config::get('module.current_module_id') == $module->id?'active':''}}">
                                            <div class="img w--70px ">
                                                <img src="{{\App\CentralLogics\Helpers::onerror_image_helper($module?->icon, asset('storage/app/public/module/').'/' . $module?->icon, asset('public/assets/admin/img/new-img/module/e-shop.svg') ,'module/')}}"

                                                     data-onerror-image="{{asset('public/assets/admin/img/new-img/module/e-shop.svg')}}"
                                                alt="new-img" class="mw-100 onerror-image">
                                            </div>
                                            <div>
                                                {{ $module->module_name }}
                                            </div>
                                        </a>
                                        @endforeach
                                        @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                                        <a href="{{ route('admin.business-settings.module.create') }}" class="__nav-module-item" data-toggle="tooltip"
                                        data-placement="top" title="{{ translate('add_new_module') }}">
                                            <i class="tio-add display-3"></i>
                                        </a>
                                        @endif
                                </div>
                            </div>
                            @else
                            <div class="__nav-module-body text-center py-5">
                                <img class="w--120px" src="{{ asset('/public/assets/admin/img/empty-box.png') }}" alt="">
                                <h2 class="my-4">{{ translate('Please, Enable or Create Module First') }}</h2>
                                <a href="{{ route('admin.business-settings.module.index') }}" class="btn btn--primary">{{ translate('messages.Module Setup') }}</a>
                            </div>
                            @endif
                        </div>
                    </li>
                    @php($mod_service = \App\Models\Module::find(Config::get('module.current_module_id')))
                    <li class="nav-item __nav-item">
                        <a href="javascript:void(0)" class="__nav-link module--nav-icon" id="tourb-0">
                            @if ($mod_service ->module_type == 'services')
                            <img  src="{{\App\CentralLogics\Helpers::onerror_image_helper($mod_service->icon, asset('storage/app/public/module/').'/' . $mod_service->icon, asset('/public/assets/admin/img/new-img/module-icon.svg') ,'module/')}}"
                                    class="onerror-image"
                                  data-onerror-image="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}"
                           width="20px" alt="public/img">
                            @else
                            <img src="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}" alt="public/img">
                            @endif
                            <span class="text-white">{{ $mod_service->module_type == 'services' ? $mod_service->module_name : translate('services') }}</span>
                            <img  src="{{asset('/public/assets/admin/img/new-img/angle-white.svg')}}" class="d-none d-lg-block ml-xl-2" alt="public/img">
                        </a>
                        <div class="__nav-module style-2" id="tourb-1">
                            @php($modules_service = \App\Models\Module::when(auth('admin')->user()->zone_id, function($query){
                                $query->whereHas('zones',function($query){
                                    $query->where('zone_id',auth('admin')->user()->zone_id);
                                });
                            })->where('module_type','services')->Active()->get())
                            @if(isset($modules_service) && ($modules_service->count()>0))
                            <div class="__nav-module-header">
                                <div class="inner">
                                    <h4>{{translate('Service Section')}}</h4>
                                    <p>
                                        {{translate('Select Module & Monitor your business module wise')}}
                                    </p>
                                </div>
                            </div>
                            <div class="__nav-module-body">
                                <div class="__nav-module-items">
                                    @foreach ($modules_service as $module_servive)
                                        <a href="javascript:"

                                            data-module-id="{{ $module_servive->id }}"
                                            data-url="{{route('admin.dashboard')}}"
                                            data-filter="module_id"

                                        class="__nav-module-item set-module-service {{Config::get('module.current_module_id') == $module_servive->id?'active':''}}">
                                            <div class="img w--70px ">
                                                <img src="{{\App\CentralLogics\Helpers::onerror_image_helper($module_servive?->icon, asset('storage/app/public/module/').'/' . $module_servive?->icon, asset('public/assets/admin/img/new-img/module/e-shop.svg') ,'module/')}}"

                                                     data-onerror-image="{{asset('public/assets/admin/img/new-img/module/e-shop.svg')}}"
                                                alt="new-img" class="mw-100 onerror-image">
                                            </div>
                                            <div>
                                                {{ $module_servive->module_name }}
                                            </div>
                                        </a>
                                        @endforeach
                                        @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                                        <a href="{{ route('admin.business-settings.module.create') }}" class="__nav-module-item" data-toggle="tooltip"
                                        data-placement="top" title="{{ translate('add_new_module') }}">
                                            <i class="tio-add display-3"></i>
                                        </a>
                                        @endif
                                </div>
                            </div>
                            @else
                            <div class="__nav-module-body text-center py-5">
                                <img class="w--120px" src="{{ asset('/public/assets/admin/img/empty-box.png') }}" alt="">
                                <h2 class="my-4">{{ translate('Please, Enable or Create Module First') }}</h2>
                                <a href="{{ route('admin.business-settings.module.index') }}" class="btn btn--primary">{{ translate('messages.Module Setup') }}</a>
                            </div>
                            @endif
                        </div>
                    </li>
                </ul>
                <!-- End Navbar -->
            </div>
            <!-- End Secondary Content -->
        </div>
    </header>
</div>
<div id="headerFluid" class="d-none"></div>
<div id="headerDouble" class="d-none"></div>

{{-- <div class="toggle-tour">
    <a href="https://youtube.com/playlist?list=PLLFMbDpKMZBxgtX3n3rKJvO5tlU8-ae2Y" target="_blank" class="d-flex align-items-center gap-10px">
        <img src="{{ asset('public/assets/admin/img/tutorial.svg') }}" alt="">
        <span>
            <span class="text-capitalize">{{ translate('Turotial') }}</span>
        </span>
    </a>
    <div class="d-flex align-items-center gap-10px restart-Tour" >
        <img src="{{ asset('public/assets/admin/img/tour.svg') }}" alt="">
        <span>
            <span class="text-capitalize">{{ translate('Tour') }}</span>
        </span>
    </div>
</div> --}}
