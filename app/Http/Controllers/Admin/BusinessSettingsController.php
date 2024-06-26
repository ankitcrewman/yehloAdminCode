<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Item;
use App\Models\Setting;
use App\Models\DeliveryPromotion;
use App\Models\DeliveryFaq;
use App\Models\Currency;
use App\Traits\Processor;
use App\Models\DataSetting;
use App\Models\TempProduct;
use App\Models\Translation;
use App\Models\AdminFeature;
use App\Models\RefundReason;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\AdminTestimonial;
use App\Models\ReactTestimonial;
use App\Models\OrderCancelReason;
use Illuminate\Support\Facades\DB;
use App\Models\NotificationMessage;
use App\Http\Controllers\Controller;
use App\Models\AdminSpecialCriteria;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use App\Models\AdminPromotionalBanner;
use App\Models\DeliveryHistory;
use App\Models\FlutterSpecialCriteria;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BusinessSettingsController extends Controller
{
    use Processor;

    public function business_index(Request $request, $tab = 'business')
    {
        if (!Helpers::module_permission_check('settings')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }
        $type = $request->type;
        if ($tab == 'business') {
            return view('admin-views.business-settings.business-index');
        } else if ($tab == 'customer') {
            $data = BusinessSetting::where('key', 'like', 'wallet_%')
                ->orWhere('key', 'like', 'loyalty_%')
                ->orWhere('key', 'like', 'ref_earning_%')
                ->orWhere('key', 'like', 'add_fund_status%')
                ->orWhere('key', 'like', 'customer_%')
                ->orWhere('key', 'like', 'ref_earning_%')->get();
            $data = array_column($data->toArray(), 'value', 'key');
            return view('admin-views.business-settings.customer-index', compact('data'));
        } else if ($tab == 'deliveryman') {
            return view('admin-views.business-settings.deliveryman-index');
        } else if ($tab == 'order') {
            $reasons = OrderCancelReason::when($request->type && ($request->type != 'all'), function ($query) use ($request) {
                $query->where('user_type', $request->type);
            })->latest()->paginate(config('default_pagination'));
            return view('admin-views.business-settings.order-index', compact('reasons', 'type'));
        } else if ($tab == 'store') {
            return view('admin-views.business-settings.store-index');
        } else if ($tab == 'refund-settings') {
            $refund_active_status = BusinessSetting::where(['key' => 'refund_active_status'])->first();
            $reasons = RefundReason::orderBy('id', 'desc')
                ->paginate(config('default_pagination'));
            return view('admin-views.business-settings.refund-index', compact('refund_active_status', 'reasons'));
        } else if ($tab == 'landing-page') {
            $landing = BusinessSetting::where('key', 'landing_page')->exists();
            if (!$landing) {
                Helpers::insert_business_settings_key('landing_page', '1');
                Helpers::insert_business_settings_key('landing_integration_type', 'none');
            }
            return view('admin-views.business-settings.landing-index');
        } else if ($tab == 'websocket') {
            return view('admin-views.business-settings.websocket-index');
        } else if ($tab == 'disbursement') {
            return view('admin-views.business-settings.disbursement-index');
        }
    }

    public function update_dm(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        BusinessSetting::updateOrInsert(['key' => 'min_amount_to_pay_dm'], [
            'value' => $request['min_amount_to_pay_dm']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'cash_in_hand_overflow_delivery_man'], [
            'value' => $request['cash_in_hand_overflow_delivery_man'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'dm_max_cash_in_hand'], [
            'value' => $request['dm_max_cash_in_hand']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'dm_tips_status'], [
            'value' => $request['dm_tips_status']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'dm_maximum_orders'], [
            'value' => $request['dm_maximum_orders']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'canceled_by_deliveryman'], [
            'value' => $request['canceled_by_deliveryman']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'show_dm_earning'], [
            'value' => $request['show_dm_earning']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'toggle_dm_registration'], [
            'value' => $request['dm_self_registration']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'dm_picture_upload_status'], [
            'value' => $request['dm_picture_upload_status']
        ]);

        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }

    public function update_websocket(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        DB::table('business_settings')->updateOrInsert(['key' => 'websocket_status'], [
            'value' => $request['websocket_status']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'websocket_url'], [
            'value' => $request['websocket_url']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'websocket_port'], [
            'value' => $request['websocket_port']
        ]);

        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }

    public function update_store(Request $request)
    {

        if ($request['product_approval'] == null) {
            $this->product_approval_all();
        }
        if ($request['product_approval'] == 1) {
            if (!($request->Update_product_price || $request->Add_new_product || $request->Update_product_variation || $request->Update_anything_in_product_details)) {
                DB::table('business_settings')->updateOrInsert(['key' => 'product_approval'], [
                    'value' => 0
                ]);
                Toastr::error(translate('messages.need_to_check_minimum_1_criteria_for_product_approval'));
                return back();
            }
        }
        BusinessSetting::updateOrInsert(['key' => 'cash_in_hand_overflow_store'], [
            'value' => $request['cash_in_hand_overflow_store'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'cash_in_hand_overflow_store_amount'], [
            'value' => $request['cash_in_hand_overflow_store_amount']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'min_amount_to_pay_store'], [
            'value' => $request['min_amount_to_pay_store']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'canceled_by_store'], [
            'value' => $request['canceled_by_store']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'toggle_store_registration'], [
            'value' => $request['store_self_registration']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'product_approval'], [
            'value' => $request['product_approval']
        ]);

        $values = [
            'Update_product_price' => $request->Update_product_price ?? 0,
            'Add_new_product' => $request->Add_new_product ?? 0,
            'Update_product_variation' => $request->Update_product_variation ?? 0,
            'Update_anything_in_product_details' => $request->Update_anything_in_product_details ?? 0,
        ];

        DB::table('business_settings')->updateOrInsert(['key' => 'product_approval_datas'], [
            'value' => json_encode($values)
        ]);


        DB::table('business_settings')->updateOrInsert(['key' => 'access_all_products'], [
            'value' => $request['access_all_products']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'product_gallery'], [
            'value' => $request['product_gallery']
        ]);



        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    public function update_order(Request $request)
    {
        $request->validate([
            'home_delivery_status' => 'required_without:takeaway_status',
            'takeaway_status' => 'required_without:home_delivery_status',
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'order_delivery_verification'], [
            'value' => $request['odc']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'schedule_order'], [
            'value' => $request['schedule_order']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'prescription_order_status'], [
            'value' => $request['prescription_order_status']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'home_delivery_status'], [
            'value' => $request['home_delivery_status']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'takeaway_status'], [
            'value' => $request['takeaway_status']
        ]);

        $time =  $request['schedule_order_slot_duration'];
        if ($request['schedule_order_slot_duration_time_format'] == 'hour') {
            $time =  $request['schedule_order_slot_duration'] * 60;
        }
        BusinessSetting::updateOrInsert(['key' => 'schedule_order_slot_duration'], [
            'value' => $time
        ]);
        BusinessSetting::updateOrInsert(['key' => 'schedule_order_slot_duration_time_format'], [
            'value' => $request['schedule_order_slot_duration_time_format']
        ]);

        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }


    public function update_disbursement(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        BusinessSetting::updateOrInsert(['key' => 'disbursement_type'], [
            'value' => $request['disbursement_type']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'store_disbursement_time_period'], [
            'value' => $request['store_disbursement_time_period']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'store_disbursement_week_start'], [
            'value' => $request['store_disbursement_week_start']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'store_disbursement_waiting_time'], [
            'value' => $request['store_disbursement_waiting_time']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'store_disbursement_create_time'], [
            'value' => $request['store_disbursement_create_time']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'store_disbursement_min_amount'], [
            'value' => $request['store_disbursement_min_amount']
        ]);

        BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_time_period'], [
            'value' => $request['dm_disbursement_time_period']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_week_start'], [
            'value' => $request['dm_disbursement_week_start']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_waiting_time'], [
            'value' => $request['dm_disbursement_waiting_time']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_create_time'], [
            'value' => $request['dm_disbursement_create_time']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_min_amount'], [
            'value' => $request['dm_disbursement_min_amount']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'system_php_path'], [
            'value' => $request['system_php_path']
        ]);

        if (function_exists('exec')) {
            $data = self::generateCronCommand(disbursement_type: $request['disbursement_type']);
            $scriptPath = 'script.sh';
            exec('sh ' . $scriptPath);
            BusinessSetting::updateOrInsert(['key' => 'store_disbursement_command'], [
                'value' => $data['storeCronCommand']
            ]);
            BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_command'], [
                'value' => $data['dmCronCommand']
            ]);
            Toastr::success(translate('messages.successfully_updated_disbursement_functionality'));
            return back();
        } else {
            $data = self::generateCronCommand(disbursement_type: $request['disbursement_type']);
            BusinessSetting::updateOrInsert(['key' => 'store_disbursement_command'], [
                'value' => $data['storeCronCommand']
            ]);
            BusinessSetting::updateOrInsert(['key' => 'dm_disbursement_command'], [
                'value' => $data['dmCronCommand']
            ]);
            if ($request['disbursement_type'] == 'automated') {
                Session::flash('disbursement_exec', true);
                Toastr::warning(translate('messages.Servers_PHP_exec_function_is_disabled_check_dependencies_&_start_cron_job_manualy_in_server'));
            }
            Toastr::success(translate('messages.successfully_updated_disbursement_functionality'));
            return back();
        }
    }

    private function dmSchedule()
    {
        $key = [
            'dm_disbursement_time_period', 'dm_disbursement_week_start', 'dm_disbursement_create_time'
        ];
        $settings =  array_column(BusinessSetting::whereIn('key', $key)->get()->toArray(), 'value', 'key');

        $scheduleFrequency = $settings['dm_disbursement_time_period'] ?? 'daily';
        $weekDay = $settings['dm_disbursement_week_start'] ?? 'sunday';
        $time = $settings['dm_disbursement_create_time'] ?? '12:00';


        $time = explode(":", $time);

        $hour = $time[0];
        $min = $time[1];

        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day = array_search($weekDay, $days);
        $schedule = "* * * * *";
        if ($scheduleFrequency == 'daily') {
            $schedule =  $min . " " . $hour . " " . "* * *";
        } elseif ($scheduleFrequency == 'weekly') {

            $schedule =  $min . " " . $hour . " " . "* * " . $day;
        } elseif ($scheduleFrequency == 'monthly') {
            $schedule =  $min . " " . $hour . " " . "28-31 * *";
        }
        return $schedule;
    }

    private function storeSchedule()
    {
        $key = [
            'store_disbursement_time_period', 'store_disbursement_week_start', 'store_disbursement_create_time'
        ];
        $settings =  array_column(BusinessSetting::whereIn('key', $key)->get()->toArray(), 'value', 'key');

        $scheduleFrequency = $settings['store_disbursement_time_period'] ?? 'daily';
        $weekDay = $settings['store_disbursement_week_start'] ?? 'sunday';
        $time = $settings['store_disbursement_create_time'] ?? '12:00';


        $time = explode(":", $time);

        $hour = $time[0];
        $min = $time[1];

        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day = array_search($weekDay, $days);
        $schedule = "* * * * *";
        if ($scheduleFrequency == 'daily') {
            $schedule =  $min . " " . $hour . " " . "* * *";
        } elseif ($scheduleFrequency == 'weekly') {

            $schedule =  $min . " " . $hour . " " . "* * " . $day;
        } elseif ($scheduleFrequency == 'monthly') {
            $schedule =  $min . " " . $hour . " " . "28-31 * *";
        }
        return $schedule;
    }

    private function generateCronCommand($disbursement_type = 'automated')
    {
        $system_php_path = BusinessSetting::where('key', 'system_php_path')->first();
        $system_php_path = $system_php_path ? $system_php_path->value : "/usr/bin/php";
        $dmSchedule = self::dmSchedule();
        $storeSchedule = self::storeSchedule();
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'];
        $rootPath = dirname($scriptFilename);
        $phpCommand = $system_php_path;
        $dmScriptPath = $rootPath . "/artisan dm:disbursement";
        $storeScriptPath = $rootPath . "/artisan store:disbursement";
        $dmClearCronCommand = "(crontab -l | grep -v \"$phpCommand $dmScriptPath\") | crontab -";
        $dmCronCommand = $disbursement_type == 'automated' ? "(crontab -l ; echo \"$dmSchedule $phpCommand $dmScriptPath\") | crontab -" : "";
        $storeClearCronCommand = "(crontab -l | grep -v \"$phpCommand $storeScriptPath\") | crontab -";
        $storeCronCommand = $disbursement_type == 'automated' ? "(crontab -l ; echo \"$storeSchedule $phpCommand $storeScriptPath\") | crontab -" : "";
        $scriptContent = "#!/bin/bash\n";
        $scriptContent .= $dmClearCronCommand . "\n";
        $scriptContent .= $dmCronCommand . "\n";
        $scriptContent .= $storeClearCronCommand . "\n";
        $scriptContent .= $storeCronCommand . "\n";
        $scriptFilePath = $rootPath . "/script.sh";
        file_put_contents($scriptFilePath, $scriptContent);

        return [
            'dmCronCommand' => $dmCronCommand,
            'storeCronCommand' =>  $storeCronCommand
        ];
    }

    public function business_setup(Request $request)
    {

        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }


        DB::table('business_settings')->updateOrInsert(['key' => 'business_name'], [
            'value' => $request['store_name']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'currency'], [
            'value' => $request['currency']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'timezone'], [
            'value' => $request['timezone']
        ]);

        $curr_logo = BusinessSetting::where(['key' => 'logo'])->first();
        if ($request->has('logo')) {
            $image_name = Helpers::update('business/', $curr_logo->value, 'png', $request->file('logo'));
        } else {
            $image_name = $curr_logo['value'];
        }

        DB::table('business_settings')->updateOrInsert(['key' => 'logo'], [
            'value' => $image_name
        ]);

        $fav_icon = BusinessSetting::where(['key' => 'icon'])->first();
        if ($request->has('icon')) {
            $image_name = Helpers::update('business/', $fav_icon->value, 'png', $request->file('icon'));
        } else {
            $image_name = $fav_icon['value'];
        }

        Config::set('currency', $request['currency']);
        Config::set('currency_symbol_position', $request['currency_symbol_position']);

        DB::table('business_settings')->updateOrInsert(['key' => 'site_direction'], [
            'value' => $request['site_direction']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'icon'], [
            'value' => $image_name
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'phone'], [
            'value' => $request['phone']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'email_address'], [
            'value' => $request['email']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'address'], [
            'value' => $request['address']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'footer_text'], [
            'value' => $request['footer_text']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'cookies_text'], [
            'value' => $request['cookies_text']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'minimum_shipping_charge'], [
            'value' => $request['minimum_shipping_charge']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'per_km_shipping_charge'], [
            'value' => $request['per_km_shipping_charge']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'currency_symbol_position'], [
            'value' => $request['currency_symbol_position']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'tax_included'], [
            'value' => $request['tax_included']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'order_confirmation_model'], [
            'value' => $request['order_confirmation_model']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'partial_payment_status'], [
            'value' => $request['partial_payment_status']
        ]);
        DB::table('business_settings')->updateOrInsert(['key' => 'partial_payment_method'], [
            'value' => $request['partial_payment_method']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'tax'], [
            'value' => $request['tax']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'admin_commission'], [
            'value' => $request['admin_commission']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'country'], [
            'value' => $request['country']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'default_location'], [
            'value' => json_encode(['lat' => $request['latitude'], 'lng' => $request['longitude']])
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'admin_order_notification'], [
            'value' => $request['admin_order_notification']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'order_notification_type'], [
            'value' => $request['order_notification_type']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'free_delivery_over_status'], [
            'value' => $request['free_delivery_over_status'] ? $request['free_delivery_over_status'] : null
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'free_delivery_over'], [
            'value' => $request['free_delivery_over_status'] ? $request['free_delivery_over'] : null
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'additional_charge_status'], [
            'value' => $request['additional_charge_status'] ? $request['additional_charge_status'] : null
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'additional_charge_name'], [
            'value' => $request['additional_charge_name'] ? $request['additional_charge_name'] : null
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'additional_charge'], [
            'value' => $request['additional_charge'] ? $request['additional_charge'] : null
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'guest_checkout_status'], [
            'value' => $request['guest_checkout_status'] ? $request['guest_checkout_status'] : 0
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'timeformat'], [
            'value' => $request['time_format']
        ]);



        DB::table('business_settings')->updateOrInsert(['key' => 'toggle_veg_non_veg'], [
            'value' => $request['vnv']
        ]);


        DB::table('business_settings')->updateOrInsert(['key' => 'digit_after_decimal_point'], [
            'value' => $request['digit_after_decimal_point']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'delivery_charge_comission'], [
            'value' => $request['admin_comission_in_delivery_charge']
        ]);


        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }

    public function mail_index()
    {
        return view('admin-views.business-settings.mail-index');
    }
    public function test_mail()
    {
        return view('admin-views.business-settings.send-mail-index');
    }

    public function mail_config(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        BusinessSetting::updateOrInsert(
            ['key' => 'mail_config'],
            [
                'value' => json_encode([
                    "status" => $request['status'] ?? 0,
                    "name" => $request['name'],
                    "host" => $request['host'],
                    "driver" => $request['driver'],
                    "port" => $request['port'],
                    "username" => $request['username'],
                    "email_id" => $request['email'],
                    "encryption" => $request['encryption'],
                    "password" => $request['password']
                ]),
                'updated_at' => now()
            ]
        );
        Toastr::success(translate('messages.configuration_updated_successfully'));
        return back();
    }

    public function mail_config_status(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        $config = BusinessSetting::where(['key' => 'mail_config'])->first();

        $data = $config ? json_decode($config['value'], true) : null;

        BusinessSetting::updateOrInsert(
            ['key' => 'mail_config'],
            [
                'value' => json_encode([
                    "status" => $request['status'] ?? 0,
                    "name" => $data['name'] ?? '',
                    "host" => $data['host'] ?? '',
                    "driver" => $data['driver'] ?? '',
                    "port" => $data['port'] ?? '',
                    "username" => $data['username'] ?? '',
                    "email_id" => $data['email_id'] ?? '',
                    "encryption" => $data['encryption'] ?? '',
                    "password" => $data['password'] ?? ''
                ]),
                'updated_at' => now()
            ]
        );
        Toastr::success(translate('messages.configuration_updated_successfully'));
        return back();
    }

    public function payment_index()
    {
        $published_status = 0; // Set a default value
        $payment_published_status = config('get_payment_publish_status');
        if (isset($payment_published_status[0]['is_published'])) {
            $published_status = $payment_published_status[0]['is_published'];
        }

        $routes = config('addon_admin_routes');
        $desiredName = 'payment_setup';
        $payment_url = '';

        foreach ($routes as $routeArray) {
            foreach ($routeArray as $route) {
                if ($route['name'] === $desiredName) {
                    $payment_url = $route['url'];
                    break 2;
                }
            }
        }
        $data_values = Setting::whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago', 'phonepay'])->orderby('key_name', 'desc')->get();


        return view('admin-views.business-settings.payment-index', compact('published_status', 'payment_url', 'data_values'));
    }

    public function payment_update(Request $request, $name)
    {

        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }


        if ($name == 'cash_on_delivery') {
            $payment = BusinessSetting::where('key', 'cash_on_delivery')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'cash_on_delivery',
                    'value'      => json_encode([
                        'status' => $request['status'],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'cash_on_delivery'])->update([
                    'key'        => 'cash_on_delivery',
                    'value'      => json_encode([
                        'status' => $request['status'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'digital_payment') {
            $payment = BusinessSetting::where('key', 'digital_payment')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'digital_payment',
                    'value'      => json_encode([
                        'status' => $request['status'],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'digital_payment'])->update([
                    'key'        => 'digital_payment',
                    'value'      => json_encode([
                        'status' => $request['status'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'ssl_commerz_payment') {
            $payment = BusinessSetting::where('key', 'ssl_commerz_payment')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'ssl_commerz_payment',
                    'value'      => json_encode([
                        'status'         => 1,
                        'store_id'       => '',
                        'store_password' => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'ssl_commerz_payment'])->update([
                    'key'        => 'ssl_commerz_payment',
                    'value'      => json_encode([
                        'status'         => $request['status'],
                        'store_id'       => $request['store_id'],
                        'store_password' => $request['store_password'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'razor_pay') {
            $payment = BusinessSetting::where('key', 'razor_pay')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'razor_pay',
                    'value'      => json_encode([
                        'status'       => 1,
                        'razor_key'    => '',
                        'razor_secret' => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'razor_pay'])->update([
                    'key'        => 'razor_pay',
                    'value'      => json_encode([
                        'status'       => $request['status'],
                        'razor_key'    => $request['razor_key'],
                        'razor_secret' => $request['razor_secret'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'paypal') {
            $payment = BusinessSetting::where('key', 'paypal')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'paypal',
                    'value'      => json_encode([
                        'status'           => 1,
                        'mode'              => '',
                        'paypal_client_id' => '',
                        'paypal_secret'    => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'paypal'])->update([
                    'key'        => 'paypal',
                    'value'      => json_encode([
                        'status'           => $request['status'],
                        'mode'              => $request['mode'],
                        'paypal_client_id' => $request['paypal_client_id'],
                        'paypal_secret'    => $request['paypal_secret'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'stripe') {
            $payment = BusinessSetting::where('key', 'stripe')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'stripe',
                    'value'      => json_encode([
                        'status'        => 1,
                        'api_key'       => '',
                        'published_key' => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'stripe'])->update([
                    'key'        => 'stripe',
                    'value'      => json_encode([
                        'status'        => $request['status'],
                        'api_key'       => $request['api_key'],
                        'published_key' => $request['published_key'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'senang_pay') {
            $payment = BusinessSetting::where('key', 'senang_pay')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([

                    'key'        => 'senang_pay',
                    'value'      => json_encode([
                        'status'        => 1,
                        'secret_key'    => '',
                        'published_key' => '',
                        'merchant_id' => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'senang_pay'])->update([
                    'key'        => 'senang_pay',
                    'value'      => json_encode([
                        'status'        => $request['status'],
                        'secret_key'    => $request['secret_key'],
                        'published_key' => $request['publish_key'],
                        'merchant_id' => $request['merchant_id'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'paystack') {
            $payment = BusinessSetting::where('key', 'paystack')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'paystack',
                    'value'      => json_encode([
                        'status'        => 1,
                        'publicKey'     => '',
                        'secretKey'     => '',
                        'paymentUrl'    => '',
                        'merchantEmail' => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'paystack'])->update([
                    'key'        => 'paystack',
                    'value'      => json_encode([
                        'status'        => $request['status'],
                        'publicKey'     => $request['publicKey'],
                        'secretKey'     => $request['secretKey'],
                        'paymentUrl'    => $request['paymentUrl'],
                        'merchantEmail' => $request['merchantEmail'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'flutterwave') {
            $payment = BusinessSetting::where('key', 'flutterwave')->first();
            if (isset($payment) == false) {
                DB::table('business_settings')->insert([
                    'key'        => 'flutterwave',
                    'value'      => json_encode([
                        'status'        => 1,
                        'public_key'     => '',
                        'secret_key'     => '',
                        'hash'    => '',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_settings')->where(['key' => 'flutterwave'])->update([
                    'key'        => 'flutterwave',
                    'value'      => json_encode([
                        'status'        => $request['status'],
                        'public_key'     => $request['public_key'],
                        'secret_key'     => $request['secret_key'],
                        'hash'    => $request['hash'],
                    ]),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($name == 'mercadopago') {
            $payment = BusinessSetting::updateOrInsert(
                ['key' => 'mercadopago'],
                [
                    'value'      => json_encode([
                        'status'        => $request['status'],
                        'public_key'     => $request['public_key'],
                        'access_token'     => $request['access_token'],
                    ]),
                    'updated_at' => now()
                ]
            );
        } elseif ($name == 'paymob_accept') {
            DB::table('business_settings')->updateOrInsert(['key' => 'paymob_accept'], [
                'value' => json_encode([
                    'status' => $request['status'],
                    'api_key' => $request['api_key'],
                    'iframe_id' => $request['iframe_id'],
                    'integration_id' => $request['integration_id'],
                    'hmac' => $request['hmac'],
                ]),
                'updated_at' => now()
            ]);
        } elseif ($name == 'liqpay') {
            DB::table('business_settings')->updateOrInsert(['key' => 'liqpay'], [
                'value' => json_encode([
                    'status' => $request['status'],
                    'public_key' => $request['public_key'],
                    'private_key' => $request['private_key']
                ]),
                'updated_at' => now()
            ]);
        } elseif ($name == 'paytm') {
            DB::table('business_settings')->updateOrInsert(['key' => 'paytm'], [
                'value' => json_encode([
                    'status' => $request['status'],
                    'paytm_merchant_key' => $request['paytm_merchant_key'],
                    'paytm_merchant_mid' => $request['paytm_merchant_mid'],
                    'paytm_merchant_website' => $request['paytm_merchant_website'],
                    'paytm_refund_url' => $request['paytm_refund_url'],
                ]),
                'updated_at' => now()
            ]);
        } elseif ($name == 'bkash') {
            DB::table('business_settings')->updateOrInsert(['key' => 'bkash'], [
                'value' => json_encode([
                    'status' => $request['status'],
                    'api_key' => $request['api_key'],
                    'api_secret' => $request['api_secret'],
                    'username' => $request['username'],
                    'password' => $request['password'],
                ]),
                'updated_at' => now()
            ]);
        } elseif ($name == 'paytabs') {
            DB::table('business_settings')->updateOrInsert(['key' => 'paytabs'], [
                'value' => json_encode([
                    'status' => $request['status'],
                    'profile_id' => $request['profile_id'],
                    'server_key' => $request['server_key'],
                    'base_url' => $request['base_url']
                ]),
                'updated_at' => now()
            ]);
        }

        Toastr::success(translate('messages.payment_settings_updated'));
        return back();
    }

    public function payment_config_update(Request $request)
    {
        //    dd($request);
        if ($request->toggle_type) {
            BusinessSetting::query()->updateOrInsert(['key' => $request->toggle_type], [
                'value' =>  $request->toggle_type == 'offline_payment_status' ? $request?->status : json_encode(['status' => $request?->status]),
                'updated_at' => now()
            ]);
            Toastr::success(translate('messages.payment_settings_updated'));
            return back();
        }

        $request['status'] = $request->status ?? 0;

        $validation = [
            'gateway' => 'required|in:ssl_commerz,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago,phonepay',
            'mode' => 'required|in:live,test'
        ];






        $additional_data = [];

        if ($request['gateway'] == 'ssl_commerz') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'store_id' => 'required_if:status,1',
                'store_password' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'paypal') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'client_id' => 'required_if:status,1',
                'client_secret' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'stripe') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required_if:status,1',
                'published_key' => 'required_if:status,1',
            ];
        } elseif ($request['gateway'] == 'razor_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'api_key' => 'required_if:status,1',
                'api_secret' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'senang_pay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'callback_url' => 'required_if:status,1',
                'secret_key' => 'required_if:status,1',
                'merchant_id' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'paytabs') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'profile_id' => 'required_if:status,1',
                'server_key' => 'required_if:status,1',
                'base_url' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'paystack') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'public_key' => 'required_if:status,1',
                'secret_key' => 'required_if:status,1',
                'merchant_email' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'paymob_accept') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'callback_url' => 'required_if:status,1',
                'api_key' => 'required_if:status,1',
                'iframe_id' => 'required_if:status,1',
                'integration_id' => 'required_if:status,1',
                'hmac' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'mercadopago') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'access_token' => 'required_if:status,1',
                'public_key' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'liqpay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'private_key' => 'required_if:status,1',
                'public_key' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'flutterwave') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'secret_key' => 'required_if:status,1',
                'public_key' => 'required_if:status,1',
                'hash' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'paytm') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_key' => 'required_if:status,1',
                'merchant_id' => 'required_if:status,1',
                'merchant_website_link' => 'required_if:status,1'
            ];
        } elseif ($request['gateway'] == 'bkash') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'app_key' => 'required_if:status,1',
                'app_secret' => 'required_if:status,1',
                'username' => 'required_if:status,1',
                'password' => 'required_if:status,1',
            ];
        } elseif ($request['gateway'] == 'phonepay') {
            $additional_data = [
                'status' => 'required|in:1,0',
                'merchant_id' => 'required_if:status,1',
                'salt_key' => 'required_if:status,1',
                'salt_index' => 'required_if:status,1',
                'redirect_url' => 'required_if:status,1',
                'callbackUrl' => 'required_if:status,1',
            ];
        }

        $request->validate(array_merge($validation, $additional_data));

        $settings = Setting::where('key_name', $request['gateway'])->where('settings_type', 'payment_config')->first();

        $additional_data_image = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;

        if ($request->has('gateway_image')) {
            $gateway_image = $this->file_uploader('payment_modules/gateway_image/', 'png', $request['gateway_image'], $additional_data_image != null ? $additional_data_image->gateway_image : '');
        } else {
            $gateway_image = $additional_data_image != null ? $additional_data_image->gateway_image : '';
        }

        $payment_additional_data = [
            'gateway_title' => $request['gateway_title'],
            'gateway_image' => $gateway_image,
        ];

        $validator = Validator::make($request->all(), array_merge($validation, $additional_data));


        Setting::updateOrCreate(['key_name' => $request['gateway'], 'settings_type' => 'payment_config'], [
            'key_name' => $request['gateway'],
            'live_values' => $validator->validate(),
            'test_values' => $validator->validate(),
            'settings_type' => 'payment_config',
            'mode' => $request['mode'],
            'is_active' => $request['status'],
            'additional_data' => json_encode($payment_additional_data),
        ]);

        Toastr::success(GATEWAYS_DEFAULT_UPDATE_200['message']);
        return back();
    }

    public function app_settings()
    {
        return view('admin-views.business-settings.app-settings');
    }

    public function update_app_settings(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($request->type == 'user_app') {

            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_android'], [
                'value' => $request['app_minimum_version_android']
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_ios'], [
                'value' => $request['app_minimum_version_ios']
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_android'], [
                'value' => $request['app_url_android']
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_ios'], [
                'value' => $request['app_url_ios']
            ]);

            Toastr::success(translate('messages.User_app_settings_updated'));
            return back();
        }

        if ($request->type == 'store_app') {

            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_android_store'], [
                'value' => $request['app_minimum_version_android_store']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_android_store'], [
                'value' => $request['app_url_android_store']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_ios_store'], [
                'value' => $request['app_minimum_version_ios_store']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_ios_store'], [
                'value' => $request['app_url_ios_store']
            ]);

            Toastr::success(translate('messages.Store_app_settings_updated'));
            return back();
        }


        if ($request->type == 'deliveryman_app') {

            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_android_deliveryman'], [
                'value' => $request['app_minimum_version_android_deliveryman']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_android_deliveryman'], [
                'value' => $request['app_url_android_deliveryman']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_minimum_version_ios_deliveryman'], [
                'value' => $request['app_minimum_version_ios_deliveryman']
            ]);
            DB::table('business_settings')->updateOrInsert(['key' => 'app_url_ios_deliveryman'], [
                'value' => $request['app_url_ios_deliveryman']
            ]);

            Toastr::success(translate('messages.Delivery_app_settings_updated'));
            return back();
        }

        return back();
    }

    public function update_landing_page_settings(Request $request, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'text') {
            DB::table('business_settings')->updateOrInsert(['key' => 'landing_page_text'], [
                'value' => json_encode([
                    'header_title_1' => $request['header_title_1'],
                    'header_title_2' => $request['header_title_2'],
                    'header_title_3' => $request['header_title_3'],
                    'about_title' => $request['about_title'],
                    'why_choose_us' => $request['why_choose_us'],
                    'why_choose_us_title' => $request['why_choose_us_title'],
                    'module_section_title' => $request['module_section_title'],
                    'module_section_sub_title' => $request['module_section_sub_title'],
                    'refer_section_title' => $request['refer_section_title'],
                    'refer_section_sub_title' => $request['refer_section_sub_title'],
                    'refer_section_description' => $request['refer_section_description'],
                    'joinus_section_title' => $request['joinus_section_title'],
                    'joinus_section_sub_title' => $request['joinus_section_sub_title'],
                    'download_app_section_title' => $request['download_app_section_title'],
                    'download_app_section_sub_title' => $request['download_app_section_sub_title'],
                    'testimonial_title' => $request['testimonial_title'],
                    'mobile_app_section_heading' => $request['mobile_app_section_heading'],
                    'mobile_app_section_text' => $request['mobile_app_section_text'],
                    'feature_section_description' => $request['feature_section_description'],
                    'feature_section_title' => $request['feature_section_title'],
                    'newsletter_title' => $request['newsletter_title'],
                    'newsletter_sub_title' => $request['newsletter_sub_title'],
                    'contact_us_title' => $request['contact_us_title'],
                    'contact_us_sub_title' => $request['contact_us_sub_title'],
                    'footer_article' => $request['footer_article']
                ])
            ]);
            Toastr::success(translate('messages.landing_page_text_updated'));
        } else if ($tab == 'links') {
            DB::table('business_settings')->updateOrInsert(['key' => 'landing_page_links'], [
                'value' => json_encode([
                    'app_url_android_status' => $request['app_url_android_status'],
                    'app_url_android' => $request['app_url_android'],
                    'app_url_ios_status' => $request['app_url_ios_status'],
                    'app_url_ios' => $request['app_url_ios'],
                    'web_app_url_status' => $request['web_app_url_status'],
                    'web_app_url' => $request['web_app_url'],
                    'seller_app_url_status' => $request['seller_app_url_status'],
                    'seller_app_url' => $request['seller_app_url'],
                    'deliveryman_app_url_status' => $request['deliveryman_app_url_status'],
                    'deliveryman_app_url' => $request['deliveryman_app_url']
                ])
            ]);
            Toastr::success(translate('messages.landing_page_links_updated'));
        } else if ($tab == 'speciality') {
            $data = [];
            $imageName = null;
            $speciality = BusinessSetting::where('key', 'speciality')->first();
            if ($speciality) {
                $data = json_decode($speciality->value, true);
            }
            if ($request->has('image')) {
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
            }
            array_push($data, [
                'img' => $imageName,
                'title' => $request->speciality_title
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'speciality'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_speciality_updated'));
        } else if ($tab == 'joinas') {
            $data = [];
            $joinas = BusinessSetting::where('key', 'join_as_images')->first();
            if ($joinas) {
                $data = json_decode($joinas->value, true);
            }
            if ($request->has('seller_banner_bg')) {
                if (isset($data['seller_banner_bg']) && file_exists(public_path('assets/landing/image/' . $data['seller_banner_bg']))) {
                    unlink(public_path('assets/landing/image/' . $data['seller_banner_bg']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->seller_banner_bg->move(public_path('assets/landing/image'), $imageName);
                $data['seller_banner_bg'] = $imageName;
            }

            if ($request->has('deliveryman_banner_bg')) {
                if (isset($data['deliveryman_banner_bg']) && file_exists(public_path('assets/landing/image/' . $data['deliveryman_banner_bg']))) {
                    unlink(public_path('assets/landing/image/' . $data['deliveryman_banner_bg']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->deliveryman_banner_bg->move(public_path('assets/landing/image'), $imageName);
                $data['deliveryman_banner_bg'] = $imageName;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'join_as_images'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_image_updated'));
        } else if ($tab == 'download-section') {
            $data = [];
            $imageName = null;
            $download = BusinessSetting::where('key', 'download_app_section')->first();
            if ($download) {
                $data = json_decode($download->value, true);
            }
            if ($request->has('image')) {
                if (isset($data['img']) && file_exists(public_path('assets/landing/image/' . $data['img']))) {
                    unlink(public_path('assets/landing/image/' . $data['img']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
                $data['img'] = $imageName;
            }

            if ($request->has('description')) {
                $data['description'] = $request->description;
            }

            DB::table('business_settings')->updateOrInsert(['key' => 'download_app_section'], [
                'value' => json_encode($data)
            ]);

            Toastr::success(translate('messages.landing_page_download_app_section_updated'));
        } else if ($tab == 'counter-section') {
            DB::table('business_settings')->updateOrInsert(['key' => 'counter_section'], [
                'value' => json_encode([
                    'app_download_count_numbers' => $request['app_download_count_numbers'],
                    'seller_count_numbers' => $request['seller_count_numbers'],
                    'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                ])
            ]);

            Toastr::success(translate('messages.landing_page_counter_section_updated'));
        } else if ($tab == 'promotion-banner') {
            $data = [];
            $imageName = null;
            $promotion_banner = BusinessSetting::where('key', 'promotion_banner')->first();
            if ($promotion_banner) {
                $data = json_decode($promotion_banner->value, true);
            }
            if (count($data) >= 6) {
                Toastr::error(translate('messages.you_have_already_added_maximum_banner_image'));
                return back();
            }
            if ($request->has('image')) {
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
            }
            array_push($data, [
                'img' => $imageName,
                'title' => $request->title,
                'sub_title' => $request->sub_title,
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'promotion_banner'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
        } else if ($tab == 'module-section') {
            $request->validate([
                'module' => 'required',
                'description' => 'required'
            ]);
            $data = [];
            $imageName = null;
            $preImageName = null;
            $module_section = BusinessSetting::where('key', 'module_section')->first();
            if ($module_section) {
                $data = json_decode($module_section->value, true);
                if (isset($data[$request->module]['img'])) {
                    $preImageName = $data[$request->module]['img'];
                }
            }

            if ($request->has('image')) {
                if ($preImageName && file_exists(public_path('assets/landing/image') . $preImageName)) {
                    unlink(public_path('assets/landing/image') . $preImageName);
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
            }

            $data[$request->module] = [
                'description' => $request->description,
                'img' => $imageName ?? $preImageName
            ];

            DB::table('business_settings')->updateOrInsert(['key' => 'module_section'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_module_section_updated'));
        } else if ($tab == 'feature') {
            $data = [];
            $imageName = null;
            $feature = BusinessSetting::where('key', 'feature')->first();
            if ($feature) {
                $data = json_decode($feature->value, true);
            }
            if ($request->has('image')) {
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
            }
            array_push($data, [
                'img' => $imageName,
                'title' => $request->feature_title,
                'feature_description' => $request->feature_description
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'feature'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_feature_updated'));
        } else if ($tab == 'testimonial') {
            $data = [];
            $imageName = null;
            $brandImageName = null;
            $testimonial = BusinessSetting::where('key', 'testimonial')->first();
            if ($testimonial) {
                $data = json_decode($testimonial->value, true);
            }
            if ($request->has('image')) {
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->image->move(public_path('assets/landing/image'), $imageName);
            }
            if ($request->has('brand_image')) {
                $brandImageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->brand_image->move(public_path('assets/landing/image'), $brandImageName);
            }
            array_push($data, [
                'img' => $imageName,
                'brand_image' => $brandImageName,
                'name' => $request->reviewer_name,
                'position' => $request->reviewer_designation,
                'detail' => $request->review,
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'testimonial'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_testimonial_updated'));
        } else if ($tab == 'image') {
            $data = [];
            $images = BusinessSetting::where('key', 'landing_page_images')->first();
            if ($images) {
                $data = json_decode($images->value, true);
            }
            if ($request->has('top_content_image')) {
                if (isset($data['top_content_image']) && file_exists(public_path('assets/landing/image/' . $data['top_content_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['top_content_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->top_content_image->move(public_path('assets/landing/image'), $imageName);
                $data['top_content_image'] = $imageName;
            }
            if ($request->has('about_us_image')) {
                if (isset($data['about_us_image']) && file_exists(public_path('assets/landing/image/' . $data['about_us_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['about_us_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->about_us_image->move(public_path('assets/landing/image'), $imageName);
                $data['about_us_image'] = $imageName;
            }

            if ($request->has('feature_section_image')) {
                if (isset($data['feature_section_image']) && file_exists(public_path('assets/landing/image/' . $data['feature_section_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['feature_section_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->feature_section_image->move(public_path('assets/landing/image'), $imageName);
                $data['feature_section_image'] = $imageName;
            }
            if ($request->has('mobile_app_section_image')) {
                if (isset($data['mobile_app_section_image']) && file_exists(public_path('assets/landing/image/' . $data['mobile_app_section_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['mobile_app_section_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->mobile_app_section_image->move(public_path('assets/landing/image'), $imageName);
                $data['mobile_app_section_image'] = $imageName;
            }

            if ($request->has('contact_us_image')) {
                if (isset($data['contact_us_image']) && file_exists(public_path('assets/landing/image/' . $data['contact_us_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['contact_us_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->contact_us_image->move(public_path('assets/landing/image'), $imageName);
                $data['contact_us_image'] = $imageName;
            }

            DB::table('business_settings')->updateOrInsert(['key' => 'landing_page_images'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_image_updated'));
        } else if ($tab == 'background-change') {
            DB::table('business_settings')->updateOrInsert(['key' => 'backgroundChange'], [
                'value' => json_encode([
                    'primary_1_hex' => $request['header-bg'],
                    'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                    'primary_2_hex' => $request['footer-bg'],
                    'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                ])
            ]);
            Toastr::success(translate('messages.background_updated'));
        } else if ($tab == 'web-app') {
            $data = [];
            $images = BusinessSetting::where('key', 'web_app_landing_page_settings')->first();
            if ($images) {
                $data = json_decode($images->value, true);
            }
            if ($request->has('top_content_image')) {
                if (isset($data['top_content_image']) && file_exists(public_path('assets/landing/image/' . $data['top_content_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['top_content_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->top_content_image->move(public_path('assets/landing/image'), $imageName);
                $data['top_content_image'] = $imageName;
            }

            if ($request->has('mobile_app_section_image')) {
                if (isset($data['mobile_app_section_image']) && file_exists(public_path('assets/landing/image/' . $data['mobile_app_section_image']))) {
                    unlink(public_path('assets/landing/image/' . $data['mobile_app_section_image']));
                }
                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                $request->mobile_app_section_image->move(public_path('assets/landing/image'), $imageName);
                $data['mobile_app_section_image'] = $imageName;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'web_app_landing_page_settings'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.web_app_landing_page_settings'));
        } else if ($tab == 'react_header') {
            $data = null;
            $image = BusinessSetting::where('key', 'react_header_banner')->first();
            if ($image) {
                $data = $image->value;
            }
            $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
            if ($request->has('react_header_banner')) {
                // $image_name = ;
                $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('react_header_banner')) ?? null;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'react_header_banner'], [
                'value' => $data
            ]);
            Toastr::success(translate('Landing page header banner updated'));
        } else if ($tab == 'hero-section') {
            $data = [];
            $hero_section = BusinessSetting::where('key', 'hero_section')->first();
            $data = [
                'hero_section_heading' => $request->hero_section_heading ?? $hero_section['hero_section_heading'],
                'hero_section_slogan' => $request->hero_section_slogan ?? $hero_section['hero_section_slogan'],
                'hero_section_short_description' => $request->hero_section_short_description ?? $hero_section['hero_section_short_description'],
            ];
            DB::table('business_settings')->updateOrInsert(['key' => 'hero_section'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_hero_section_updated'));
        } else if ($tab == 'full-banner') {
            $data = [];
            $banner_section_full = BusinessSetting::where('key', 'banner_section_full')->first();
            $imageName = null;
            if ($banner_section_full) {
                $data = json_decode($banner_section_full->value, true);
                $imageName = $data['banner_section_img_full'] ?? null;
            }
            if ($request->has('banner_section_img_full')) {
                if (empty($imageName)) {
                    $imageName = Helpers::upload('react_landing/', 'png', $request->file('banner_section_img_full'));
                } else {
                    $imageName = Helpers::update('react_landing/', $data['banner_section_img_full'], 'png', $request->file('banner_section_img_full'));
                }
            }
            $data = [
                'banner_section_img_full' => $imageName,
                'full_banner_section_title' => $request->full_banner_section_title ?? $banner_section_full['full_banner_section_title'],
                'full_banner_section_sub_title' => $request->full_banner_section_sub_title ?? $banner_section_full['full_banner_section_sub_title'],
            ];
            DB::table('business_settings')->updateOrInsert(['key' => 'banner_section_full'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_banner_section_updated'));
        } else if ($tab == 'delivery-service-section') {
            $data = [];
            $delivery_service_section = BusinessSetting::where('key', 'delivery_service_section')->first();
            $imageName = null;
            if ($delivery_service_section) {
                $data = json_decode($delivery_service_section->value, true);
                $imageName = $data['delivery_service_section_image'] ?? null;
            }
            if ($request->has('delivery_service_section_image')) {
                if (empty($imageName)) {
                    $imageName = Helpers::upload('react_landing/', 'png', $request->file('delivery_service_section_image'));
                } else {
                    $imageName = Helpers::update('react_landing/', $data['delivery_service_section_image'], 'png', $request->file('delivery_service_section_image'));
                }
            }
            $data = [
                'delivery_service_section_image' => $imageName,
                'delivery_service_section_title' => $request->delivery_service_section_title ?? $delivery_service_section['delivery_service_section_title'],
                'delivery_service_section_description' => $request->delivery_service_section_description ?? $delivery_service_section['delivery_service_section_description'],
            ];
            DB::table('business_settings')->updateOrInsert(['key' => 'delivery_service_section'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_delivery_service_section_updated'));
        } else if ($tab == 'discount-banner') {
            $data = [];
            $discount_banner = BusinessSetting::where('key', 'discount_banner')->first();
            $imageName = null;
            if ($discount_banner) {
                $data = json_decode($discount_banner->value, true);
                $imageName = $data['img'] ?? null;
            }
            if ($request->has('img')) {
                if (empty($imageName)) {
                    $imageName = Helpers::upload('react_landing/', 'png', $request->file('img'));
                } else {
                    $imageName = Helpers::update('react_landing/', $data['img'], 'png', $request->file('img'));
                }
            }
            $data = [
                'img' => $imageName,
                'title' => $request->title ?? $discount_banner['title'],
                'sub_title' => $request->sub_title ?? $discount_banner['sub_title'],
            ];
            DB::table('business_settings')->updateOrInsert(['key' => 'discount_banner'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_discount_banner_section_updated'));
        } else if ($tab == 'banner-section-half') {

            $data = [];
            $imageName = null;
            $banner_section_half = BusinessSetting::where('key', 'banner_section_half')->first();
            if ($banner_section_half) {
                $data = json_decode($banner_section_half->value, true);
            }

            foreach ($request->banner_section_half  as $key => $value) {

                if ($request->hasfile("banner_section_half.{$key}.img")) {
                    if (isset($data[$key]['img']) && Storage::disk('public')->exists('react_landing/' . $data[$key]['img'])) {
                        Storage::disk('public')->delete('react_landing/' . $data[$key]['img']);
                    }

                    $value['img'] = Helpers::upload('react_landing/', 'png', $request->file("banner_section_half.{$key}.img"));
                } elseif (isset($data[$key]['img'])) {
                    $value['img'] = $data[$key]['img'];
                } else {
                    $value['img'] = null;
                }
                $data[$key] = $value;
            }

            DB::table('business_settings')->updateOrInsert(['key' => 'banner_section_half'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_banner_section_updated'));
        } else if ($tab == 'app_section_image') {
            $data = null;
            $image = BusinessSetting::where('key', 'app_section_image')->first();
            if ($image) {
                $data = $image->value;
            }
            $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
            if ($request->has('app_section_image')) {
                $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('app_section_image')) ?? null;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'app_section_image'], [
                'value' => $data
            ]);
            Toastr::success(translate('App section image updated'));
        } else if ($tab == 'footer_logo') {
            $data = null;
            $image = BusinessSetting::where('key', 'footer_logo')->first();
            if ($image) {
                $data = $image->value;
            }
            $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
            if ($request->has('footer_logo')) {
                $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('footer_logo')) ?? null;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'footer_logo'], [
                'value' => $data
            ]);
            Toastr::success(translate('Footer logo updated'));
        } else if ($tab == 'react-feature') {
            $data = [];
            $imageName = null;
            $feature = BusinessSetting::where('key', 'react_feature')->first();
            if ($feature) {
                $data = json_decode($feature->value, true);
            }
            if ($request->has('image')) {
                $imageName = Helpers::upload('react_landing/feature/', 'png', $request->file('image'));
            }
            array_push($data, [
                'img' => $imageName,
                'title' => $request->feature_title,
                'feature_description' => $request->feature_description
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'react_feature'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.landing_page_feature_updated'));
        } else if ($tab == 'app-download-button') {
            $data = [];
            $feature = BusinessSetting::where('key', 'app_download_button')->first();
            if ($feature) {
                $data = json_decode($feature->value, true);
            }
            array_push($data, [
                'button_text' => $request->button_text,
                'link' => $request->link
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'app_download_button'], [
                'value' => json_encode($data)
            ]);
            Toastr::success(translate('messages.app_download_button_updated'));
        }
        return back();
    }

    public function delete_landing_page_settings($tab, $key)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        $item = BusinessSetting::where('key', $tab)->first();
        $data = $item ? json_decode($item->value, true) : null;
        if ($data && array_key_exists($key, $data)) {
            if (isset($data[$key]['img']) && file_exists(public_path('assets/landing/image') . $data[$key]['img'])) {
                unlink(public_path('assets/landing/image') . $data[$key]['img']);
            }
            array_splice($data, $key, 1);

            $item->value = json_encode($data);
            $item->save();
            Toastr::success(translate('messages.' . $tab) . ' ' . translate('messages.deleted'));
            return back();
        }
        Toastr::error(translate('messages.not_found'));
        return back();
    }


    public function currency_index()
    {
        return view('admin-views.business-settings.currency-index');
    }

    public function currency_store(Request $request)
    {
        $request->validate([
            'currency_code' => 'required|unique:currencies',
        ]);

        Currency::create([
            "country" => $request['country'],
            "currency_code" => $request['currency_code'],
            "currency_symbol" => $request['symbol'],
            "exchange_rate" => $request['exchange_rate'],
        ]);
        Toastr::success(translate('messages.currency_added_successfully'));
        return back();
    }

    public function currency_edit($id)
    {
        $currency = Currency::find($id);
        return view('admin-views.business-settings.currency-update', compact('currency'));
    }

    public function currency_update(Request $request, $id)
    {
        Currency::where(['id' => $id])->update([
            "country" => $request['country'],
            "currency_code" => $request['currency_code'],
            "currency_symbol" => $request['symbol'],
            "exchange_rate" => $request['exchange_rate'],
        ]);
        Toastr::success(translate('messages.currency_updated_successfully'));
        return redirect('store-panel/business-settings/currency-add');
    }

    public function currency_delete($id)
    {
        Currency::where(['id' => $id])->delete();
        Toastr::success(translate('messages.currency_deleted_successfully'));
        return back();
    }

    private function update_data($request, $key_data)
    {
        $data = DataSetting::firstOrNew(
            [
                'key' =>  $key_data,
                'type' =>  'admin_landing_page'
            ],
        );

        //  dd($request);

        $data->value = $request->{$key_data}[array_search('default', $request->lang)];
        $data->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->{$key_data}[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\DataSetting',
                            'translationable_id' => $data->id,
                            'locale' => $key,
                            'key' => $key_data
                        ],
                        ['value' => $data->getRawOriginal('value')]
                    );
                }
            } else {
                if ($request->{$key_data}[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\DataSetting',
                            'translationable_id' => $data->id,
                            'locale' => $key,
                            'key' => $key_data
                        ],
                        ['value' => $request->{$key_data}[$index]]
                    );
                }
            }
        }

        return true;
    }


    private function policy_status_update($key_data, $status)
    {
        $data = DataSetting::firstOrNew(
            [
                'key' =>  $key_data,
                'type' =>  'admin_landing_page'
            ],
        );
        $data->value = $status;
        $data->save();

        return true;
    }


    public function terms_and_conditions()
    {
        $terms_and_conditions = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'terms_and_conditions')->first();
        return view('admin-views.business-settings.terms-and-conditions', compact('terms_and_conditions'));
    }

    public function terms_and_conditions_update(Request $request)
    {
        $this->update_data($request, 'terms_and_conditions');
        Toastr::success(translate('messages.terms_and_condition_updated'));
        return back();
    }

    public function privacy_policy()
    {
        $privacy_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'privacy_policy')->first();
        return view('admin-views.business-settings.privacy-policy', compact('privacy_policy'));
    }


    public function privacy_policy_update(Request $request)
    {
        $this->update_data($request, 'privacy_policy');
        Toastr::success(translate('messages.privacy_policy_updated'));
        return back();
    }

    public function delivery_privacy_policy()
    {
        $delivery_privacy_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'delivery_privacy_policy')->first();
        return view('admin-views.business-settings.deliveryman-privacy-policy', compact('delivery_privacy_policy'));
    }

    public function delivery_privacy_policy_update(Request $request)
    {
        // dd($request);
        $this->update_data($request, 'delivery_privacy_policy');
        Toastr::success(translate('messages.privacy_policy_updated'));
        return back();
    }

    public function refund_policy()
    {
        $refund_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'refund_policy')->first();
        $refund_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'refund_policy_status')->first();
        return view('admin-views.business-settings.refund_policy', compact('refund_policy', 'refund_policy_status'));
    }

    public function refund_update(Request $request)
    {
        $this->update_data($request, 'refund_policy');
        Toastr::success(translate('messages.refund_policy_updated'));
        return back();
    }
    public function refund_policy_status($status)
    {
        $this->policy_status_update('refund_policy_status', $status);
        return response()->json(['status' => "changed"]);
    }

    public function shipping_policy()
    {

        $shipping_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'shipping_policy')->first();
        $shipping_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'shipping_policy_status')->first();
        return view('admin-views.business-settings.shipping_policy', compact('shipping_policy', 'shipping_policy_status'));
    }

    public function shipping_policy_update(Request $request)
    {
        $this->update_data($request, 'shipping_policy');
        Toastr::success(translate('messages.shipping_policy_updated'));
        return back();
    }


    public function shipping_policy_status($status)
    {
        $this->policy_status_update('shipping_policy_status', $status);
        return response()->json(['status' => "changed"]);
    }

    public function cancellation_policy()
    {
        $cancellation_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'cancellation_policy')->first();
        $cancellation_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'cancellation_policy_status')->first();
        return view('admin-views.business-settings.cancelation_policy', compact('cancellation_policy', 'cancellation_policy_status'));
    }

    public function cancellation_policy_update(Request $request)
    {
        $this->update_data($request, 'cancellation_policy');
        Toastr::success(translate('messages.cancellation_policy_updated'));
        return back();
    }

    public function cancellation_policy_status($status)
    {
        $this->policy_status_update('cancellation_policy_status', $status);
        return response()->json(['status' => "changed"]);
    }

    public function about_us()
    {
        $about_us = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'about_us')->first();
        $about_title = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'about_title')->first();
        return view('admin-views.business-settings.about-us', compact('about_us', 'about_title'));
    }

    public function about_us_update(Request $request)
    {
        // dd($request->all());
        $this->update_data($request, 'about_us');
        $this->update_data($request, 'about_title');
        Toastr::success(translate('messages.about_us_updated'));
        return back();
    }

    public function fcm_index()
    {
        $fcm_credentials = Helpers::get_business_settings('fcm_credentials');
        return view('admin-views.business-settings.fcm-index', compact('fcm_credentials'));
    }

    public function fcm_config()
    {
        $fcm_credentials = Helpers::get_business_settings('fcm_credentials');
        return view('admin-views.business-settings.fcm-config', compact('fcm_credentials'));
    }

    public function update_fcm(Request $request)
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'fcm_project_id'], [
            'value' => $request['projectId']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'push_notification_key'], [
            'value' => $request['push_notification_key']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'fcm_credentials'], [
            'value' => json_encode([
                'apiKey' => $request->apiKey,
                'authDomain' => $request->authDomain,
                'projectId' => $request->projectId,
                'storageBucket' => $request->storageBucket,
                'messagingSenderId' => $request->messagingSenderId,
                'appId' => $request->appId,
                'measurementId' => $request->measurementId
            ])
        ]);
        Toastr::success(translate('messages.settings_updated'));
        return back();
    }

    public function update_fcm_messages(Request $request)
    {
        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_pending_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'order_pending_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->pending_message[array_search('en', $request->lang)];
        $notification->status = $request['pending_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->pending_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->pending_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_confirmation_msg')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'order_confirmation_msg';
        $notification->module_type = $request->module_type;
        $notification->message = $request->confirm_message[array_search('en', $request->lang)];
        $notification->status = $request['confirm_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->confirm_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->confirm_message[$index]]
                );
            }
        }
        if ($request->module_type != 'parcel') {


            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_processing_message')->first();
            if ($notification == null) {
                $notification = new NotificationMessage();
            }

            $notification->key = 'order_processing_message';
            $notification->module_type = $request->module_type;
            $notification->message = $request->processing_message[array_search('en', $request->lang)];
            $notification->status = $request['processing_status'] == 1 ? 1 : 0;
            $notification->save();
            foreach ($request->lang as $index => $key) {
                if ($request->processing_message[$index]) {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\NotificationMessage',
                            'translationable_id'    => $notification->id,
                            'locale'                => $key,
                            'key'                   => $notification->key
                        ],
                        ['value'                 => $request->processing_message[$index]]
                    );
                }
            }

            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_handover_message')->first();
            if ($notification == null) {
                $notification = new NotificationMessage();
            }

            $notification->key = 'order_handover_message';
            $notification->module_type = $request->module_type;
            $notification->message = $request->order_handover_message[array_search('en', $request->lang)];
            $notification->status = $request['order_handover_message_status'] == 1 ? 1 : 0;
            $notification->save();
            foreach ($request->lang as $index => $key) {
                if ($request->order_handover_message[$index]) {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\NotificationMessage',
                            'translationable_id'    => $notification->id,
                            'locale'                => $key,
                            'key'                   => $notification->key
                        ],
                        ['value'                 => $request->order_handover_message[$index]]
                    );
                }
            }

            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_refunded_message')->first();
            if ($notification == null) {
                $notification = new NotificationMessage();
            }

            $notification->key = 'order_refunded_message';
            $notification->module_type = $request->module_type;
            $notification->message = $request->order_refunded_message[array_search('en', $request->lang)];
            $notification->status = $request['order_refunded_message_status'] == 1 ? 1 : 0;
            $notification->save();
            foreach ($request->lang as $index => $key) {
                if ($request->order_refunded_message[$index]) {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\NotificationMessage',
                            'translationable_id'    => $notification->id,
                            'locale'                => $key,
                            'key'                   => $notification->key
                        ],
                        ['value'                 => $request->order_refunded_message[$index]]
                    );
                }
            }

            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'refund_request_canceled')->first();

            if ($notification == null) {
                $notification = new NotificationMessage();
            }

            $notification->key = 'refund_request_canceled';
            $notification->module_type = $request->module_type;
            $notification->message = $request->refund_request_canceled[array_search('en', $request->lang)];
            $notification->status = $request['refund_request_canceled_status'] == 1 ? 1 : 0;
            $notification->save();
            foreach ($request->lang as $index => $key) {
                if ($request->refund_request_canceled[$index]) {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\NotificationMessage',
                            'translationable_id'    => $notification->id,
                            'locale'                => $key,
                            'key'                   => $notification->key
                        ],
                        ['value'                 => $request->refund_request_canceled[$index]]
                    );
                }
            }
        }


        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'out_for_delivery_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'out_for_delivery_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->out_for_delivery_message[array_search('en', $request->lang)];
        $notification->status = $request['out_for_delivery_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->out_for_delivery_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->out_for_delivery_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_delivered_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'order_delivered_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->delivered_message[array_search('en', $request->lang)];
        $notification->status = $request['delivered_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->delivered_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->delivered_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'delivery_boy_assign_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'delivery_boy_assign_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->delivery_boy_assign_message[array_search('en', $request->lang)];
        $notification->status = $request['delivery_boy_assign_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->delivery_boy_assign_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->delivery_boy_assign_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'delivery_boy_delivered_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'delivery_boy_delivered_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->delivery_boy_delivered_message[array_search('en', $request->lang)];
        $notification->status = $request['delivery_boy_delivered_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->delivery_boy_delivered_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->delivery_boy_delivered_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_cancled_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'order_cancled_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->order_cancled_message[array_search('en', $request->lang)];
        $notification->status = $request['order_cancled_message_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->order_cancled_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->order_cancled_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'offline_order_accept_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'offline_order_accept_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->offline_order_accept_message[array_search('en', $request->lang)];
        $notification->status = $request['offline_order_accept_message_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->offline_order_accept_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->offline_order_accept_message[$index]]
                );
            }
        }

        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'offline_order_deny_message')->first();
        if ($notification == null) {
            $notification = new NotificationMessage();
        }

        $notification->key = 'offline_order_deny_message';
        $notification->module_type = $request->module_type;
        $notification->message = $request->offline_order_deny_message[array_search('en', $request->lang)];
        $notification->status = $request['offline_order_deny_message_status'] == 1 ? 1 : 0;
        $notification->save();
        foreach ($request->lang as $index => $key) {
            if ($request->offline_order_deny_message[$index]) {
                Translation::updateOrInsert(
                    [
                        'translationable_type'  => 'App\Models\NotificationMessage',
                        'translationable_id'    => $notification->id,
                        'locale'                => $key,
                        'key'                   => $notification->key
                    ],
                    ['value'                 => $request->offline_order_deny_message[$index]]
                );
            }
        }


        Toastr::success(translate('messages.message_updated'));
        return back();
    }



    public function location_setup(Request $request)
    {
        $store = Helpers::get_store_id();
        $store->latitude = $request['latitude'];
        $store->longitude = $request['longitude'];
        $store->save();

        Toastr::success(translate('messages.settings_updated'));
        return back();
    }

    public function config_setup()
    {
        return view('admin-views.business-settings.config');
    }

    public function config_update(Request $request)
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'map_api_key'], [
            'value' => $request['map_api_key']
        ]);

        DB::table('business_settings')->updateOrInsert(['key' => 'map_api_key_server'], [
            'value' => $request['map_api_key_server']
        ]);

        Toastr::success(translate('messages.config_data_updated'));
        return back();
    }

    public function toggle_settings($key, $value)
    {
        DB::table('business_settings')->updateOrInsert(['key' => $key], [
            'value' => $value
        ]);

        Toastr::success(translate('messages.app_settings_updated'));
        return back();
    }

    public function viewSocialLogin()
    {
        $data = BusinessSetting::where('key', 'social_login')->first();
        if (!$data) {
            Helpers::insert_business_settings_key('social_login', '[{"login_medium":"google","client_id":"","client_secret":"","status":"0"},{"login_medium":"facebook","client_id":"","client_secret":"","status":""}]');
            $data = BusinessSetting::where('key', 'social_login')->first();
        }
        $apple = BusinessSetting::where('key', 'apple_login')->first();
        if (!$apple) {
            Helpers::insert_business_settings_key('apple_login', '[{"login_medium":"apple","client_id":"","client_secret":"","team_id":"","key_id":"","service_file":"","redirect_url":"","status":""}]');
            $apple = BusinessSetting::where('key', 'apple_login')->first();
        }
        $appleLoginServices = json_decode($apple->value, true);
        $socialLoginServices = json_decode($data->value, true);
        return view('admin-views.business-settings.social-login.view', compact('socialLoginServices', 'appleLoginServices'));
    }

    public function updateSocialLogin($service, Request $request)
    {
        $socialLogin = BusinessSetting::where('key', 'social_login')->first();
        $credential_array = [];
        foreach (json_decode($socialLogin['value'], true) as $key => $data) {
            if ($data['login_medium'] == $service) {
                $cred = [
                    'login_medium' => $service,
                    'client_id' => $request['client_id'],
                    'client_secret' => $request['client_secret'],
                    'status' => $request['status'],
                ];
                array_push($credential_array, $cred);
            } else {
                array_push($credential_array, $data);
            }
        }
        BusinessSetting::where('key', 'social_login')->update([
            'value' => $credential_array
        ]);

        Toastr::success(translate('messages.credential_updated', ['service' => $service]));
        return redirect()->back();
    }
    public function updateAppleLogin($service, Request $request)
    {
        $appleLogin = BusinessSetting::where('key', 'apple_login')->first();
        $credential_array = [];
        if ($request->hasfile('service_file')) {
            $fileName = Helpers::upload('apple-login/', 'p8', $request->file('service_file'));
        }
        foreach (json_decode($appleLogin['value'], true) as $key => $data) {
            if ($data['login_medium'] == $service) {
                $cred = [
                    'login_medium' => $service,
                    'client_id' => $request['client_id'],
                    'client_secret' => $request['client_secret'],
                    'status' => $request['status'],
                    'team_id' => $request['team_id'],
                    'key_id' => $request['key_id'],
                    'service_file' => isset($fileName) ? $fileName : $data['service_file'],
                    'redirect_url' => $request['redirect_url'],
                ];
                array_push($credential_array, $cred);
            } else {
                array_push($credential_array, $data);
            }
        }
        BusinessSetting::where('key', 'apple_login')->update([
            'value' => $credential_array
        ]);

        Toastr::success(translate('messages.credential_updated', ['service' => $service]));
        return redirect()->back();
    }

    //recaptcha
    public function recaptcha_index(Request $request)
    {
        return view('admin-views.business-settings.recaptcha-index');
    }

    public function recaptcha_update(Request $request)
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'recaptcha'], [
            'key' => 'recaptcha',
            'value' => json_encode([
                'status' => $request['status'],
                'site_key' => $request['site_key'],
                'secret_key' => $request['secret_key']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }
    //Send Mail
    public function send_mail(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        $response_flag = 0;
        try {
            Mail::to($request->email)->send(new \App\Mail\TestEmailSender());
            $response_flag = 1;
        } catch (\Exception $exception) {
            info($exception->getMessage());
            dd($exception->getMessage());
            $response_flag = 2;
        }

        return response()->json(['success' => $response_flag]);
    }


    public function site_direction(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            session()->put('site_direction', ($request->status == 1 ? 'ltr' : 'rtl'));
            return response()->json();
        }
        if ($request->status == 1) {
            DB::table('business_settings')->updateOrInsert(['key' => 'site_direction'], [
                'value' => 'ltr'
            ]);
        } else {
            DB::table('business_settings')->updateOrInsert(['key' => 'site_direction'], [
                'value' => 'rtl'
            ]);
        }
        return;
    }

    public function vendor_landing_page_settings($tab)
    {
        if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.vendor-fixed-data');
        } else if ($tab == 'promotional-section') {
            return view('admin-views.business-settings.landing-page-settings.vendor-promotional-section');
        } else if ($tab == 'feature-list') {
            return view('admin-views.business-settings.landing-page-settings.vendor-feature-list');
        } else if ($tab == 'earn-money') {
            return view('admin-views.business-settings.landing-page-settings.vendor-earn-money');
        } else if ($tab == 'why-choose-us') {
            return view('admin-views.business-settings.landing-page-settings.vendor-landing-why-choose');
        } else if ($tab == 'download-apps') {
            return view('admin-views.business-settings.landing-page-settings.vendor-landing-download-apps');
        } else if ($tab == 'testimonials') {
            return view('admin-views.business-settings.landing-page-settings.vendor-landing-testimonial');
        } else if ($tab == 'contact-us') {
            return view('admin-views.business-settings.landing-page-settings.vendor-landing-contact');
        } else if ($tab == 'background-color') {
            return view('admin-views.business-settings.landing-page-settings.vendor-landing-background-color');
        }
    }


    public function admin_landing_page_settings($tab)
    {
        if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.admin-fixed-data');
        } else if ($tab == 'promotional-section') {
            return view('admin-views.business-settings.landing-page-settings.admin-promotional-section');
        } else if ($tab == 'feature-list') {
            return view('admin-views.business-settings.landing-page-settings.admin-feature-list');
        } else if ($tab == 'earn-money') {
            return view('admin-views.business-settings.landing-page-settings.admin-earn-money');
        } else if ($tab == 'why-choose-us') {
            return view('admin-views.business-settings.landing-page-settings.admin-landing-why-choose');
        } else if ($tab == 'download-apps') {
            return view('admin-views.business-settings.landing-page-settings.admin-landing-download-apps');
        } else if ($tab == 'testimonials') {
            return view('admin-views.business-settings.landing-page-settings.admin-landing-testimonial');
        } else if ($tab == 'contact-us') {
            return view('admin-views.business-settings.landing-page-settings.admin-landing-contact');
        } else if ($tab == 'delivery-man-setting') {
            return view('admin-views.business-settings.landing-page-settings.admin-delivery-man-setting');
        } else if ($tab == 'background-color') {
            return view('admin-views.business-settings.landing-page-settings.admin-landing-background-color');
        }
    }


    public function delivery_landing_page_settings($tab)
    {
        if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.delivery-fixed-data');
        } else if ($tab == 'promotional-section') {
            return view('admin-views.business-settings.landing-page-settings.delivery-promotional-section');
        }
        else if ($tab == 'faq') {
            return view('admin-views.business-settings.landing-page-settings.delivery-faq');
        }
    }



    public function update_delivery_landing_page_settings(Request $request, $tab)
    {
        if ($tab == 'fixed-data') {
            $delivery_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_heading')->first();
            if ($delivery_heading == null) {
                $delivery_heading = new DataSetting();
            }

            $delivery_heading->key = 'delivery_heading';
            $delivery_heading->type = 'delivery_landing_page';
            $delivery_heading->value = $request->delivery_heading[0];
            $delivery_heading->save();

            $delivery_sub_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_sub_heading')->first();
            if ($delivery_sub_heading == null) {
                $delivery_sub_heading = new DataSetting();
            }

            $delivery_sub_heading->key = 'delivery_sub_heading';
            $delivery_sub_heading->type = 'delivery_landing_page';
            $delivery_sub_heading->value = $request->delivery_sub_heading[0];
            $delivery_sub_heading->save();





            $delivery_head_image = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_head_image')->first();
            if ($delivery_head_image == null) {
                $delivery_head_image = new DataSetting();
            }
            $delivery_head_image->key = 'delivery_head_image';
            $delivery_head_image->type = 'delivery_landing_page';
            $delivery_head_image->value = $request->has('delivery_head_image') ? Helpers::update('deliveryman/', $delivery_head_image->value, 'png', $request->file('delivery_head_image')) : $delivery_head_image->value;
            $delivery_head_image->save();

            Toastr::success("Delivery Home Setting update");
            return back();
        } else if ($tab == 'footer-fixed-data') {



            $delivery_footer_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_heading')->first();
            if ($delivery_footer_heading == null) {
                $delivery_footer_heading = new DataSetting();
            }

            $delivery_footer_heading->key = 'delivery_footer_heading';
            $delivery_footer_heading->type = 'delivery_landing_page';
            $delivery_footer_heading->value = $request->delivery_footer_heading[0];
            $delivery_footer_heading->save();

            $delivery_footer_sub_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_sub_heading')->first();
            if ($delivery_footer_sub_heading == null) {
                $delivery_footer_sub_heading = new DataSetting();
            }

            $delivery_footer_sub_heading->key = 'delivery_footer_sub_heading';
            $delivery_footer_sub_heading->type = 'delivery_landing_page';
            $delivery_footer_sub_heading->value = $request->delivery_footer_sub_heading[0];
            $delivery_footer_sub_heading->save();


            $sub_head_2 = DataSetting::where('type', 'delivery_landing_page')->where('key', 'sub_head_2')->first();
            if ($sub_head_2 == null) {
                $sub_head_2 = new DataSetting();
            }

            $sub_head_2->key = 'sub_head_2';
            $sub_head_2->type = 'delivery_landing_page';
            $sub_head_2->value = $request->sub_head_2[0];
            $sub_head_2->save();


            $download_link_button = DataSetting::where('type', 'delivery_landing_page')->where('key', 'download_link_button')->first();
            if ($download_link_button == null) {
                $download_link_button = new DataSetting();
            }

            $download_link_button->key = 'download_link_button';
            $download_link_button->type = 'delivery_landing_page';
            $download_link_button->value = $request->download_link_button[0];
            $download_link_button->save();



            $delivery_footer_image = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_image')->first();
            if ($delivery_footer_image == null) {
                $delivery_footer_image = new DataSetting();
            }
            $delivery_footer_image->key = 'delivery_footer_image';
            $delivery_footer_image->type = 'delivery_landing_page';
            $delivery_footer_image->value = $request->has('delivery_footer_image') ? Helpers::update('deliveryman/', $delivery_footer_image->value, 'png', $request->file('delivery_footer_image')) : $delivery_footer_image->value;
            $delivery_footer_image->save();

            Toastr::success("Delivery Footer Setting update");
            return back();
        } else if ($tab == 'promotional-tab') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',  // Adjust the image validation as needed
            ]);

            // Check if a DeliveryPromotion record with main_heading exists
            $promotion_main_heading = DeliveryPromotion::whereNotNull('main_heading')->first();
            if ($promotion_main_heading == null) {
                // If no record exists, create a new one
                $promotion_main_heading = new DeliveryPromotion();
                $promotion_main_heading->main_heading = $request->main_title[0];
                $promotion_main_heading->title = $request->title[0];
                $promotion_main_heading->subtitle = $request->sub_title[0];
                $promotion_main_heading->image = Helpers::upload('delivery_promotion/', 'png', $request->file('image'));
                $promotion_main_heading->save();
            } else if ($promotion_main_heading != null) {
                $new_promotion = new DeliveryPromotion();
                $new_promotion->title = $request->title[0];
                $new_promotion->subtitle = $request->sub_title[0];
                $new_promotion->image = Helpers::upload('delivery_promotion/', 'png', $request->file('image'));
                $new_promotion->save();
            }
            return back();
        }
        else if($tab == 'faq'){
            $request->validate([
                'questions' => 'required',
                'anwser' => 'required',
            ]);
            $faq = new DeliveryFaq();
            $faq->question = $request->questions[0];
            $faq->anwser = $request->anwser[0];
            $faq->save();
            return back();
        }
    }

    public function update_admin_landing_page_settings(Request $request, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }



        if ($tab == 'fixed-data') {
            $fixed_header_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_title')->first();
            if ($fixed_header_title == null) {
                $fixed_header_title = new DataSetting();
            }

            $fixed_header_title->key = 'fixed_header_title';
            $fixed_header_title->type = 'admin_landing_page';
            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
            $fixed_header_title->save();

            $fixed_header_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_sub_title')->first();
            if ($fixed_header_sub_title == null) {
                $fixed_header_sub_title = new DataSetting();
            }

            $fixed_header_sub_title->key = 'fixed_header_sub_title';
            $fixed_header_sub_title->type = 'admin_landing_page';
            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
            $fixed_header_sub_title->save();

            $fixed_module_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_title')->first();
            if ($fixed_module_title == null) {
                $fixed_module_title = new DataSetting();
            }

            $fixed_module_title->key = 'fixed_module_title';
            $fixed_module_title->type = 'admin_landing_page';
            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
            $fixed_module_title->save();

            $fixed_module_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_sub_title')->first();
            if ($fixed_module_sub_title == null) {
                $fixed_module_sub_title = new DataSetting();
            }

            $fixed_module_sub_title->key = 'fixed_module_sub_title';
            $fixed_module_sub_title->type = 'admin_landing_page';
            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
            $fixed_module_sub_title->save();

            $fixed_referal_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_referal_title')->first();
            if ($fixed_referal_title == null) {
                $fixed_referal_title = new DataSetting();
            }

            $fixed_referal_title->key = 'fixed_referal_title';
            $fixed_referal_title->type = 'admin_landing_page';
            $fixed_referal_title->value = $request->fixed_referal_title[array_search('default', $request->lang)];
            $fixed_referal_title->save();

            $fixed_referal_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_referal_sub_title')->first();
            if ($fixed_referal_sub_title == null) {
                $fixed_referal_sub_title = new DataSetting();
            }

            $fixed_referal_sub_title->key = 'fixed_referal_sub_title';
            $fixed_referal_sub_title->type = 'admin_landing_page';
            $fixed_referal_sub_title->value = $request->fixed_referal_sub_title[array_search('default', $request->lang)];
            $fixed_referal_sub_title->save();

            $fixed_newsletter_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_title')->first();
            if ($fixed_newsletter_title == null) {
                $fixed_newsletter_title = new DataSetting();
            }

            $fixed_newsletter_title->key = 'fixed_newsletter_title';
            $fixed_newsletter_title->type = 'admin_landing_page';
            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
            $fixed_newsletter_title->save();

            $fixed_newsletter_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
            if ($fixed_newsletter_sub_title == null) {
                $fixed_newsletter_sub_title = new DataSetting();
            }

            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
            $fixed_newsletter_sub_title->type = 'admin_landing_page';
            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
            $fixed_newsletter_sub_title->save();

            $fixed_footer_article_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_footer_article_title')->first();
            if ($fixed_footer_article_title == null) {
                $fixed_footer_article_title = new DataSetting();
            }

            $fixed_footer_article_title->key = 'fixed_footer_article_title';
            $fixed_footer_article_title->type = 'admin_landing_page';
            $fixed_footer_article_title->value = $request->fixed_footer_article_title[array_search('default', $request->lang)];
            $fixed_footer_article_title->save();
            // dd($fixed_module_sub_title?->getRawOriginal('value'));



            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $request->fixed_header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $request->fixed_header_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $request->fixed_module_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $request->fixed_module_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_referal_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title'
                            ],
                            ['value' => $fixed_referal_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_referal_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title'
                            ],
                            ['value' => $request->fixed_referal_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_referal_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_sub_title'
                            ],
                            ['value' => $fixed_referal_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_referal_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_sub_title'
                            ],
                            ['value' => $request->fixed_referal_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $request->fixed_newsletter_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_footer_article_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title'
                            ],
                            ['value' => $fixed_footer_article_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_footer_article_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title'
                            ],
                            ['value' => $request->fixed_footer_article_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'fixed_link', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'web_app_url_status' => $request['web_app_url_status'],
                    'web_app_url' => $request['web_app_url'],
                ])
            ]);
            Toastr::success(translate('messages.landing_page_text_updated'));
        } elseif ($tab == 'promotional-section') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $banner = new AdminPromotionalBanner();
            $banner->title = $request->title[array_search('default', $request->lang)];
            $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $banner->image = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
            $banner->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $banner->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $banner->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.banner_added_successfully'));
            return back();
        } elseif ($tab == 'feature-list') {


            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',

                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $feature = new AdminFeature();

            $feature->title = $request->title[array_search('default', $request->lang)];
            $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $feature->sub_title_2 = $request->sub_title2[array_search('default', $request->lang)];
            $feature->sub_title_3 = $request->sub_title3[array_search('default', $request->lang)];
            $feature->image = Helpers::upload('admin_feature/', 'png', $request->file('image'));
            $feature->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $feature->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $feature->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }

                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title2'
                            ],
                            ['value'                 => $feature->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title2'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title3'
                            ],
                            ['value'                 => $feature->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title3'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.feature_added_successfully'));
        } elseif ($tab == 'feature-title') {
            $feature_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_title')->first();
            if ($feature_title == null) {
                $feature_title = new DataSetting();
            }

            $feature_title->key = 'feature_title';
            $feature_title->type = 'admin_landing_page';
            $feature_title->value = $request->feature_title[array_search('default', $request->lang)];
            $feature_title->save();

            $feature_short_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_short_description')->first();
            if ($feature_short_description == null) {
                $feature_short_description = new DataSetting();
            }

            $feature_short_description->key = 'feature_short_description';
            $feature_short_description->type = 'admin_landing_page';
            $feature_short_description->value = $request->feature_short_description[array_search('default', $request->lang)];
            $feature_short_description->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->feature_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title'
                            ],
                            ['value' => $feature_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title'
                            ],
                            ['value' => $request->feature_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->feature_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description'
                            ],
                            ['value' => $feature_short_description->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description'
                            ],
                            ['value' => $request->feature_short_description[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.feature_section_updated'));
        } elseif ($tab == 'earning-title') {
            $earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_title')->first();
            if ($earning_title == null) {
                $earning_title = new DataSetting();
            }

            $earning_title->key = 'earning_title';
            $earning_title->type = 'admin_landing_page';
            $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
            $earning_title->save();

            $earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_sub_title')->first();
            if ($earning_sub_title == null) {
                $earning_sub_title = new DataSetting();
            }

            $earning_sub_title->key = 'earning_sub_title';
            $earning_sub_title->type = 'admin_landing_page';
            $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
            $earning_sub_title->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $request->earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $request->earning_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.earning_section_updated'));
        } elseif ($tab == 'seller-purchase') {

            $seller_purchase = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_purchase')->first();
            if ($seller_purchase == null) {
                $seller_purchase = new DataSetting();
            }

            $seller_purchase->key = 'seller_purchase';
            $seller_purchase->type = 'admin_landing_page';
            $seller_purchase->value = $request->seller_purchase[array_search('default', $request->lang)];
            $seller_purchase->save();

            $seller_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_description')->first();
            if ($seller_description == null) {
                $seller_description = new DataSetting();
            }

            $seller_description->key = 'seller_description';
            $seller_description->type = 'admin_landing_page';
            $seller_description->value = $request->seller_description[array_search('default', $request->lang)];
            $seller_description->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            // foreach ($request->lang as $index => $key) {
            //     if ($default_lang == $key && !($request->earning_title[$index])) {
            //         if ($key != 'default') {
            //             Translation::updateOrInsert(
            //                 [
            //                     'translationable_type' => 'App\Models\DataSetting',
            //                     'translationable_id' => $earning_title->id,
            //                     'locale' => $key,
            //                     'key' => 'earning_title'
            //                 ],
            //                 ['value' => $earning_title?->getRawOriginal('value')]
            //             );
            //         }
            //     } else {
            //         if ($request->earning_title[$index] && $key != 'default') {
            //             Translation::updateOrInsert(
            //                 [
            //                     'translationable_type' => 'App\Models\DataSetting',
            //                     'translationable_id' => $earning_title->id,
            //                     'locale' => $key,
            //                     'key' => 'earning_title'
            //                 ],
            //                 ['value' => $request->earning_title[$index]]
            //             );
            //         }
            //     }
            //     if ($default_lang == $key && !($request->earning_sub_title[$index])) {
            //         if ($key != 'default') {
            //             Translation::updateOrInsert(
            //                 [
            //                     'translationable_type' => 'App\Models\DataSetting',
            //                     'translationable_id' => $earning_sub_title->id,
            //                     'locale' => $key,
            //                     'key' => 'earning_sub_title'
            //                 ],
            //                 ['value' => $earning_sub_title?->getRawOriginal('value')]
            //             );
            //         }
            //     } else {
            //         if ($request->earning_sub_title[$index] && $key != 'default') {
            //             Translation::updateOrInsert(
            //                 [
            //                     'translationable_type' => 'App\Models\DataSetting',
            //                     'translationable_id' => $earning_sub_title->id,
            //                     'locale' => $key,
            //                     'key' => 'earning_sub_title'
            //                 ],
            //                 ['value' => $request->earning_sub_title[$index]]
            //             );
            //         }
            //     }
            // }

            Toastr::success('Purchase Section Update');
        } elseif ($tab == 'seller-vendor') {

            // dd($request);

            $home_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'home_title')->first();
            if ($home_title == null) {
                $home_title = new DataSetting();
            }

            $home_title->key = 'home_title';
            $home_title->type = 'admin_landing_page';
            $home_title->value = $request->home_title[array_search('default', $request->lang)];
            $home_title->save();

            /////

            $home_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'home_sub_title')->first();
            if ($home_sub_title == null) {
                $home_sub_title = new DataSetting();
            }

            $home_sub_title->key = 'home_sub_title';
            $home_sub_title->type = 'admin_landing_page';
            $home_sub_title->value = $request->home_sub_title[array_search('default', $request->lang)];
            $home_sub_title->save();
            ////////

            $home_heading = DataSetting::where('type', 'admin_landing_page')->where('key', 'home_heading')->first();
            if ($home_heading == null) {
                $home_heading = new DataSetting();
            }

            $home_heading->key = 'home_heading';
            $home_heading->type = 'admin_landing_page';
            $home_heading->value = $request->home_heading[array_search('default', $request->lang)];
            $home_heading->save();
            ////////////


            $home_sub_heading = DataSetting::where('type', 'admin_landing_page')->where('key', 'home_sub_heading')->first();
            if ($home_sub_heading == null) {
                $home_sub_heading = new DataSetting();
            }

            $home_sub_heading->key = 'home_sub_heading';
            $home_sub_heading->type = 'admin_landing_page';
            $home_sub_heading->value = $request->home_sub_heading[array_search('default', $request->lang)];
            $home_sub_heading->save();

            // dd($request);


            $home_seller_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'home_seller_image')->first();
            if ($home_seller_image == null) {
                $home_seller_image = new DataSetting();
            }
            $home_seller_image->key = 'home_seller_image';
            $home_seller_image->type = 'admin_landing_page';
            $home_seller_image->value = $request->has('home_seller_image') ? Helpers::update('earning/', $home_seller_image->value, 'png', $request->file('home_seller_image')) : $home_seller_image->value;
            $home_seller_image->save();

            Toastr::success("Home Setting update");
        } elseif ($tab == 'seller-vendor-footer') {

            //  dd($request);

            $seller_footer_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_footer_title')->first();
            if ($seller_footer_title == null) {
                $seller_footer_title = new DataSetting();
            }

            $seller_footer_title->key = 'seller_footer_title';
            $seller_footer_title->type = 'admin_landing_page';
            $seller_footer_title->value = $request->seller_footer_title[array_search('default', $request->lang)];
            $seller_footer_title->save();

            /////

            $seller_footer_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_footer_sub_title')->first();
            if ($seller_footer_sub_title == null) {
                $seller_footer_sub_title = new DataSetting();
            }

            $seller_footer_sub_title->key = 'seller_footer_sub_title';
            $seller_footer_sub_title->type = 'admin_landing_page';
            $seller_footer_sub_title->value = $request->seller_footer_sub_title[array_search('default', $request->lang)];
            $seller_footer_sub_title->save();
            ////////

            $seller_footer_heading = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_footer_heading')->first();
            if ($seller_footer_heading == null) {
                $seller_footer_heading = new DataSetting();
            }

            $seller_footer_heading->key = 'seller_footer_heading';
            $seller_footer_heading->type = 'admin_landing_page';
            $seller_footer_heading->value = $request->seller_footer_heading[array_search('default', $request->lang)];
            $seller_footer_heading->save();
            ////////////


            $seller_footer_sub_heading = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_footer_sub_heading')->first();
            if ($seller_footer_sub_heading == null) {
                $seller_footer_sub_heading = new DataSetting();
            }

            $seller_footer_sub_heading->key = 'seller_footer_sub_heading';
            $seller_footer_sub_heading->type = 'admin_landing_page';
            $seller_footer_sub_heading->value = $request->seller_footer_sub_heading[array_search('default', $request->lang)];
            $seller_footer_sub_heading->save();

            // dd($request);


            $footer_seller_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'footer_seller_image')->first();
            if ($footer_seller_image == null) {
                $footer_seller_image = new DataSetting();
            }
            $footer_seller_image->key = 'footer_seller_image';
            $footer_seller_image->type = 'admin_landing_page';
            $footer_seller_image->value = $request->has('footer_seller_image') ? Helpers::update('earning/', $footer_seller_image->value, 'png', $request->file('footer_seller_image')) : $footer_seller_image->value;
            $footer_seller_image->save();

            Toastr::success("Footer Setting update");
        } elseif ($tab == 'deliveryman-header') {

            //  dd($request);

            $delivery_man_header_title = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_man_header_title')->first();
            if ($delivery_man_header_title == null) {
                $delivery_man_header_title = new DataSetting();
            }

            $delivery_man_header_title->key = 'delivery_man_header_title';
            $delivery_man_header_title->type = 'delivery_landing_page';
            $delivery_man_header_title->value = $request->delivery_man_header_title[array_search('default', $request->lang)];
            $delivery_man_header_title->save();





            $delivery_man_sub_header_title = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_man_sub_header_title')->first();
            if ($delivery_man_sub_header_title == null) {
                $delivery_man_sub_header_title = new DataSetting();
            }

            $delivery_man_sub_header_title->key = 'delivery_man_sub_header_title';
            $delivery_man_sub_header_title->type = 'delivery_landing_page';
            $delivery_man_sub_header_title->value = $request->delivery_man_sub_header_title[array_search('default', $request->lang)];
            $delivery_man_sub_header_title->save();



            $home_delivery_image = DataSetting::where('type', 'delivery_landing_page')->where('key', 'home_delivery_image')->first();
            if ($home_delivery_image == null) {
                $home_delivery_image = new DataSetting();
            }
            $home_delivery_image->key = 'home_delivery_image';
            $home_delivery_image->type = 'delivery_landing_page';
            $home_delivery_image->value = $request->has('home_delivery_image') ? Helpers::update('earning/', $home_delivery_image->value, 'png', $request->file('home_delivery_image')) : $home_delivery_image->value;
            $home_delivery_image->save();

            Toastr::success("Delivery Home Setting update");
        } elseif ($tab == 'delivery-footer') {

            // dd($request);

            $delivery_footer_title = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_title')->first();
            if ($delivery_footer_title == null) {
                $delivery_footer_title = new DataSetting();
            }

            $delivery_footer_title->key = 'delivery_footer_title';
            $delivery_footer_title->type = 'delivery_landing_page';
            $delivery_footer_title->value = $request->delivery_footer_title[array_search('default', $request->lang)];
            $delivery_footer_title->save();

            /////

            $delivery_footer_sub_title = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_sub_title')->first();
            if ($delivery_footer_sub_title == null) {
                $delivery_footer_sub_title = new DataSetting();
            }

            $delivery_footer_sub_title->key = 'delivery_footer_sub_title';
            $delivery_footer_sub_title->type = 'delivery_landing_page';
            $delivery_footer_sub_title->value = $request->delivery_footer_sub_title[array_search('default', $request->lang)];
            $delivery_footer_sub_title->save();
            ////////

            $delivery_footer_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_heading')->first();
            if ($delivery_footer_heading == null) {
                $delivery_footer_heading = new DataSetting();
            }

            $delivery_footer_heading->key = 'delivery_footer_heading';
            $delivery_footer_heading->type = 'delivery_landing_page';
            $delivery_footer_heading->value = $request->delivery_footer_heading[array_search('default', $request->lang)];
            $delivery_footer_heading->save();
            ////////////


            $delivery_footer_sub_heading = DataSetting::where('type', 'delivery_landing_page')->where('key', 'delivery_footer_sub_heading')->first();
            if ($delivery_footer_sub_heading == null) {
                $delivery_footer_sub_heading = new DataSetting();
            }

            $delivery_footer_sub_heading->key = 'delivery_footer_sub_heading';
            $delivery_footer_sub_heading->type = 'delivery_landing_page';
            $delivery_footer_sub_heading->value = $request->delivery_footer_sub_heading[array_search('default', $request->lang)];
            $delivery_footer_sub_heading->save();

            // dd($request);


            $footer_delivery_image = DataSetting::where('type', 'delivery_landing_page')->where('key', 'footer_delivery_image')->first();
            if ($footer_delivery_image == null) {
                $footer_delivery_image = new DataSetting();
            }
            $footer_delivery_image->key = 'footer_delivery_image';
            $footer_delivery_image->type = 'delivery_landing_page';
            $footer_delivery_image->value = $request->has('footer_delivery_image') ? Helpers::update('earning/', $footer_delivery_image->value, 'png', $request->file('footer_delivery_image')) : $footer_delivery_image->value;
            $footer_delivery_image->save();

            Toastr::success("Home Setting update");
        } elseif ($tab == 'earning-seller-link') {
            $earning_seller_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_seller_image')->first();
            if ($earning_seller_image == null) {
                $earning_seller_image = new DataSetting();
            }
            $earning_seller_image->key = 'earning_seller_image';
            $earning_seller_image->type = 'admin_landing_page';
            $earning_seller_image->value = $request->has('earning_seller_image') ? Helpers::update('earning/', $earning_seller_image->value, 'png', $request->file('earning_seller_image')) : $earning_seller_image->value;
            $earning_seller_image->save();
            DB::table('data_settings')->updateOrInsert(['key' => 'seller_app_earning_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);
            Toastr::success(translate('messages.seller_links_updated'));
        } elseif ($tab == 'earning-dm-link') {
            $earning_delivery_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_delivery_image')->first();
            if ($earning_delivery_image == null) {
                $earning_delivery_image = new DataSetting();
            }
            $earning_delivery_image->key = 'earning_delivery_image';
            $earning_delivery_image->type = 'admin_landing_page';
            $earning_delivery_image->value = $request->has('earning_delivery_image') ? Helpers::update('earning/', $earning_delivery_image->value, 'png', $request->file('earning_delivery_image')) : $earning_delivery_image->value;
            $earning_delivery_image->save();
            DB::table('data_settings')->updateOrInsert(['key' => 'dm_app_earning_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);
            Toastr::success(translate('messages.delivery_man_links_updated'));
        } elseif ($tab == 'why-choose-title') {
            $why_choose_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'why_choose_title')->first();
            if ($why_choose_title == null) {
                $why_choose_title = new DataSetting();
            }

            $why_choose_title->key = 'why_choose_title';
            $why_choose_title->type = 'admin_landing_page';
            $why_choose_title->value = $request->why_choose_title[array_search('default', $request->lang)];
            $why_choose_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->why_choose_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title'
                            ],
                            ['value' => $why_choose_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->why_choose_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title'
                            ],
                            ['value' => $request->why_choose_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.why_choose_section_updated'));
        } elseif ($tab == 'special-criteria-list') {
            $request->validate([
                'title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $criteria = new AdminSpecialCriteria();
            $criteria->title = $request->title[array_search('default', $request->lang)];
            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
            $criteria->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $criteria->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.criteria_added_successfully'));
        } elseif ($tab == 'download-app-section') {
            $download_user_app_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting();
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'admin_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting();
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'admin_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            $download_user_app_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_image')->first();
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting();
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'admin_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'download_user_app_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);


            Toastr::success(translate('messages.download_app_section_updated'));
        } else if ($tab == 'download-counter-section') {
            DB::table('data_settings')->updateOrInsert(['key' => 'counter_section', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'app_download_count_numbers' => $request['app_download_count_numbers'],
                    'seller_count_numbers' => $request['seller_count_numbers'],
                    'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                    'customer_count_numbers' => $request['customer_count_numbers'],
                    'status' => $request['status'],
                ])
            ]);

            Toastr::success(translate('messages.landing_page_counter_section_updated'));
        } elseif ($tab == 'testimonial-title') {
            $testimonial_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'testimonial_title')->first();
            if ($testimonial_title == null) {
                $testimonial_title = new DataSetting();
            }

            $testimonial_title->key = 'testimonial_title';
            $testimonial_title->type = 'admin_landing_page';
            $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
            $testimonial_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->testimonial_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $testimonial_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->testimonial_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $request->testimonial_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.testimonial_section_updated'));
        } elseif ($tab == 'testimonial-list') {
            $request->validate([
                'name' => 'required',
                'designation' => 'required',
                'review' => 'required',
                'reviewer_image' => 'required',
                'company_image' => 'required',
            ]);

            $testimonial = new AdminTestimonial();
            $testimonial->name = $request->name;
            $testimonial->designation = $request->designation;
            $testimonial->review = $request->review;
            $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
            $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
            $testimonial->save();
            Toastr::success(translate('messages.testimonial_added_successfully'));
        } elseif ($tab == 'contact-us-section') {
            $contact_us_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_title')->first();
            if ($contact_us_title == null) {
                $contact_us_title = new DataSetting();
            }

            $contact_us_title->key = 'contact_us_title';
            $contact_us_title->type = 'admin_landing_page';
            $contact_us_title->value = $request->contact_us_title[array_search('default', $request->lang)];
            $contact_us_title->save();

            $contact_us_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_sub_title')->first();
            if ($contact_us_sub_title == null) {
                $contact_us_sub_title = new DataSetting();
            }

            $contact_us_sub_title->key = 'contact_us_sub_title';
            $contact_us_sub_title->type = 'admin_landing_page';
            $contact_us_sub_title->value = $request->contact_us_sub_title[array_search('default', $request->lang)];
            $contact_us_sub_title->save();

            $contact_us_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_image')->first();
            if ($contact_us_image == null) {
                $contact_us_image = new DataSetting();
            }
            $contact_us_image->key = 'contact_us_image';
            $contact_us_image->type = 'admin_landing_page';
            $contact_us_image->value = $request->has('image') ? Helpers::update('contact_us_image/', $contact_us_image->value, 'png', $request->file('image')) : $contact_us_image->value;
            $contact_us_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->contact_us_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_title'
                            ],
                            ['value' => $contact_us_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->contact_us_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_title'
                            ],
                            ['value' => $request->contact_us_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->contact_us_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_sub_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_sub_title'
                            ],
                            ['value' => $contact_us_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->contact_us_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_sub_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_sub_title'
                            ],
                            ['value' => $request->contact_us_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('business_settings')->updateOrInsert(['key' => 'opening_time'], [
                'value' => $request['opening_time']
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'closing_time'], [
                'value' => $request['closing_time']
            ]);

            if ($request->opening_day == $request->closing_day) {
                Toastr::error(translate('messages.the_start_day_and_end_day_is_same'));
            } else {
                DB::table('business_settings')->updateOrInsert(['key' => 'opening_day'], [
                    'value' => $request['opening_day']
                ]);

                DB::table('business_settings')->updateOrInsert(['key' => 'closing_day'], [
                    'value' => $request['closing_day']
                ]);
            }


            Toastr::success(translate('messages.contact_section_updated'));
        } elseif ($tab == 'background-color') {
            DB::table('business_settings')->updateOrInsert(['key' => 'backgroundChange'], [
                'value' => json_encode([
                    'primary_1_hex' => $request['header-bg'],
                    'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                    'primary_2_hex' => $request['footer-bg'],
                    'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                ])
            ]);
            Toastr::success(translate('messages.background_updated'));
        }
        return back();
    }


    public function update_vendor_landing_page_settings(Request $request, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'fixed-data') {
            $fixed_header_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_header_title')->first();
            if ($fixed_header_title == null) {
                $fixed_header_title = new DataSetting();
            }

            $fixed_header_title->key = 'fixed_header_title';
            $fixed_header_title->type = 'vendor_landing_page';
            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
            $fixed_header_title->save();

            $fixed_header_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_header_sub_title')->first();
            if ($fixed_header_sub_title == null) {
                $fixed_header_sub_title = new DataSetting();
            }

            $fixed_header_sub_title->key = 'fixed_header_sub_title';
            $fixed_header_sub_title->type = 'vendor_landing_page';
            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
            $fixed_header_sub_title->save();

            $fixed_module_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_module_title')->first();
            if ($fixed_module_title == null) {
                $fixed_module_title = new DataSetting();
            }

            $fixed_module_title->key = 'fixed_module_title';
            $fixed_module_title->type = 'vendor_landing_page';
            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
            $fixed_module_title->save();

            $fixed_module_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_module_sub_title')->first();
            if ($fixed_module_sub_title == null) {
                $fixed_module_sub_title = new DataSetting();
            }

            $fixed_module_sub_title->key = 'fixed_module_sub_title';
            $fixed_module_sub_title->type = 'vendor_landing_page';
            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
            $fixed_module_sub_title->save();

            $fixed_referal_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_referal_title')->first();
            if ($fixed_referal_title == null) {
                $fixed_referal_title = new DataSetting();
            }

            $fixed_referal_title->key = 'fixed_referal_title';
            $fixed_referal_title->type = 'vendor_landing_page';
            $fixed_referal_title->value = $request->fixed_referal_title[array_search('default', $request->lang)];
            $fixed_referal_title->save();

            $fixed_referal_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_referal_sub_title')->first();
            if ($fixed_referal_sub_title == null) {
                $fixed_referal_sub_title = new DataSetting();
            }

            $fixed_referal_sub_title->key = 'fixed_referal_sub_title';
            $fixed_referal_sub_title->type = 'vendor_landing_page';
            $fixed_referal_sub_title->value = $request->fixed_referal_sub_title[array_search('default', $request->lang)];
            $fixed_referal_sub_title->save();

            $fixed_newsletter_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_newsletter_title')->first();
            if ($fixed_newsletter_title == null) {
                $fixed_newsletter_title = new DataSetting();
            }

            $fixed_newsletter_title->key = 'fixed_newsletter_title';
            $fixed_newsletter_title->type = 'vendor_landing_page';
            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
            $fixed_newsletter_title->save();

            $fixed_newsletter_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
            if ($fixed_newsletter_sub_title == null) {
                $fixed_newsletter_sub_title = new DataSetting();
            }

            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
            $fixed_newsletter_sub_title->type = 'vendor_landing_page';
            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
            $fixed_newsletter_sub_title->save();

            $fixed_footer_article_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'fixed_footer_article_title')->first();
            if ($fixed_footer_article_title == null) {
                $fixed_footer_article_title = new DataSetting();
            }

            $fixed_footer_article_title->key = 'fixed_footer_article_title';
            $fixed_footer_article_title->type = 'vendor_landing_page';
            $fixed_footer_article_title->value = $request->fixed_footer_article_title[array_search('default', $request->lang)];
            $fixed_footer_article_title->save();
            // dd($fixed_module_sub_title?->getRawOriginal('value'));

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $request->fixed_header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $request->fixed_header_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $request->fixed_module_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $request->fixed_module_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_referal_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title'
                            ],
                            ['value' => $fixed_referal_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_referal_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title'
                            ],
                            ['value' => $request->fixed_referal_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_referal_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_sub_title'
                            ],
                            ['value' => $fixed_referal_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_referal_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_sub_title'
                            ],
                            ['value' => $request->fixed_referal_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $request->fixed_newsletter_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_footer_article_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title'
                            ],
                            ['value' => $fixed_footer_article_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_footer_article_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title'
                            ],
                            ['value' => $request->fixed_footer_article_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'fixed_link', 'type' => 'vendor_landing_page'], [
                'value' => json_encode([
                    'web_app_url_status' => $request['web_app_url_status'],
                    'web_app_url' => $request['web_app_url'],
                ])
            ]);
            Toastr::success(translate('messages.landing_page_text_updated'));
        } elseif ($tab == 'promotional-section') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $banner = new AdminPromotionalBanner();
            $banner->title = $request->title[array_search('default', $request->lang)];
            $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $banner->image = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
            $banner->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $banner->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $banner->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                                'translationable_id'    => $banner->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.banner_added_successfully'));
            return back();
        } elseif ($tab == 'feature-list') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $feature = new AdminFeature();
            $feature->title = $request->title[array_search('default', $request->lang)];
            $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $feature->image = Helpers::upload('admin_feature/', 'png', $request->file('image'));
            $feature->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $feature->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $feature->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminFeature',
                                'translationable_id'    => $feature->id,
                                'locale'                => $key,
                                'key'                   => 'sub_title'
                            ],
                            ['value'                 => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.feature_added_successfully'));
        } elseif ($tab == 'feature-title') {
            $feature_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'feature_title')->first();
            if ($feature_title == null) {
                $feature_title = new DataSetting();
            }

            $feature_title->key = 'feature_title';
            $feature_title->type = 'vendor_landing_page';
            $feature_title->value = $request->feature_title[array_search('default', $request->lang)];
            $feature_title->save();

            $feature_short_description = DataSetting::where('type', 'vendor_landing_page')->where('key', 'feature_short_description')->first();
            if ($feature_short_description == null) {
                $feature_short_description = new DataSetting();
            }

            $feature_short_description->key = 'feature_short_description';
            $feature_short_description->type = 'vendor_landing_page';
            $feature_short_description->value = $request->feature_short_description[array_search('default', $request->lang)];
            $feature_short_description->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->feature_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title'
                            ],
                            ['value' => $feature_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title'
                            ],
                            ['value' => $request->feature_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->feature_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description'
                            ],
                            ['value' => $feature_short_description->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description'
                            ],
                            ['value' => $request->feature_short_description[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.feature_section_updated'));
        } elseif ($tab == 'earning-title') {
            $earning_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'earning_title')->first();
            if ($earning_title == null) {
                $earning_title = new DataSetting();
            }

            $earning_title->key = 'earning_title';
            $earning_title->type = 'vendor_landing_page';
            $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
            $earning_title->save();

            $earning_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'earning_sub_title')->first();
            if ($earning_sub_title == null) {
                $earning_sub_title = new DataSetting();
            }

            $earning_sub_title->key = 'earning_sub_title';
            $earning_sub_title->type = 'vendor_landing_page';
            $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
            $earning_sub_title->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $request->earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $request->earning_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.earning_section_updated'));
        } elseif ($tab == 'earning-seller-link') {
            $earning_seller_image = DataSetting::where('type', 'vendor_landing_page')->where('key', 'earning_seller_image')->first();
            if ($earning_seller_image == null) {
                $earning_seller_image = new DataSetting();
            }
            $earning_seller_image->key = 'earning_seller_image';
            $earning_seller_image->type = 'vendor_landing_page';
            $earning_seller_image->value = $request->has('earning_seller_image') ? Helpers::update('earning/', $earning_seller_image->value, 'png', $request->file('earning_seller_image')) : $earning_seller_image->value;
            $earning_seller_image->save();
            DB::table('data_settings')->updateOrInsert(['key' => 'seller_app_earning_links', 'type' => 'vendor_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);
            Toastr::success(translate('messages.seller_links_updated'));
        } elseif ($tab == 'earning-dm-link') {
            $earning_delivery_image = DataSetting::where('type', 'vendor_landing_page')->where('key', 'earning_delivery_image')->first();
            if ($earning_delivery_image == null) {
                $earning_delivery_image = new DataSetting();
            }
            $earning_delivery_image->key = 'earning_delivery_image';
            $earning_delivery_image->type = 'vendor_landing_page';
            $earning_delivery_image->value = $request->has('earning_delivery_image') ? Helpers::update('earning/', $earning_delivery_image->value, 'png', $request->file('earning_delivery_image')) : $earning_delivery_image->value;
            $earning_delivery_image->save();
            DB::table('data_settings')->updateOrInsert(['key' => 'dm_app_earning_links', 'type' => 'vendor_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);
            Toastr::success(translate('messages.delivery_man_links_updated'));
        } elseif ($tab == 'why-choose-title') {
            $why_choose_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'why_choose_title')->first();
            if ($why_choose_title == null) {
                $why_choose_title = new DataSetting();
            }

            $why_choose_title->key = 'why_choose_title';
            $why_choose_title->type = 'vendor_landing_page';
            $why_choose_title->value = $request->why_choose_title[array_search('default', $request->lang)];
            $why_choose_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->why_choose_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title'
                            ],
                            ['value' => $why_choose_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->why_choose_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title'
                            ],
                            ['value' => $request->why_choose_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.why_choose_section_updated'));
        } elseif ($tab == 'special-criteria-list') {
            $request->validate([
                'title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $criteria = new AdminSpecialCriteria();
            $criteria->title = $request->title[array_search('default', $request->lang)];
            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
            $criteria->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $criteria->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.criteria_added_successfully'));
        } elseif ($tab == 'download-app-section') {
            $download_user_app_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting();
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'vendor_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting();
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'vendor_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            $download_user_app_image = DataSetting::where('type', 'vendor_landing_page')->where('key', 'download_user_app_image')->first();
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting();
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'vendor_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'download_user_app_links', 'type' => 'vendor_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);


            Toastr::success(translate('messages.download_app_section_updated'));
        } else if ($tab == 'download-counter-section') {
            DB::table('data_settings')->updateOrInsert(['key' => 'counter_section', 'type' => 'vendor_landing_page'], [
                'value' => json_encode([
                    'app_download_count_numbers' => $request['app_download_count_numbers'],
                    'seller_count_numbers' => $request['seller_count_numbers'],
                    'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                    'customer_count_numbers' => $request['customer_count_numbers'],
                    'status' => $request['status'],
                ])
            ]);

            Toastr::success(translate('messages.landing_page_counter_section_updated'));
        } elseif ($tab == 'testimonial-title') {
            $testimonial_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'testimonial_title')->first();
            if ($testimonial_title == null) {
                $testimonial_title = new DataSetting();
            }

            $testimonial_title->key = 'testimonial_title';
            $testimonial_title->type = 'vendor_landing_page';
            $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
            $testimonial_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->testimonial_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $testimonial_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->testimonial_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $request->testimonial_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.testimonial_section_updated'));
        } elseif ($tab == 'testimonial-list') {
            $request->validate([
                'name' => 'required',
                'designation' => 'required',
                'review' => 'required',
                'reviewer_image' => 'required',
                'company_image' => 'required',
            ]);

            $testimonial = new AdminTestimonial();
            $testimonial->name = $request->name;
            $testimonial->designation = $request->designation;
            $testimonial->review = $request->review;
            $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
            $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
            $testimonial->save();
            Toastr::success(translate('messages.testimonial_added_successfully'));
        } elseif ($tab == 'contact-us-section') {
            $contact_us_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'contact_us_title')->first();
            if ($contact_us_title == null) {
                $contact_us_title = new DataSetting();
            }

            $contact_us_title->key = 'contact_us_title';
            $contact_us_title->type = 'vendor_landing_page';
            $contact_us_title->value = $request->contact_us_title[array_search('default', $request->lang)];
            $contact_us_title->save();

            $contact_us_sub_title = DataSetting::where('type', 'vendor_landing_page')->where('key', 'contact_us_sub_title')->first();
            if ($contact_us_sub_title == null) {
                $contact_us_sub_title = new DataSetting();
            }

            $contact_us_sub_title->key = 'contact_us_sub_title';
            $contact_us_sub_title->type = 'vendor_landing_page';
            $contact_us_sub_title->value = $request->contact_us_sub_title[array_search('default', $request->lang)];
            $contact_us_sub_title->save();

            $contact_us_image = DataSetting::where('type', 'vendor_landing_page')->where('key', 'contact_us_image')->first();
            if ($contact_us_image == null) {
                $contact_us_image = new DataSetting();
            }
            $contact_us_image->key = 'contact_us_image';
            $contact_us_image->type = 'vendor_landing_page';
            $contact_us_image->value = $request->has('image') ? Helpers::update('contact_us_image/', $contact_us_image->value, 'png', $request->file('image')) : $contact_us_image->value;
            $contact_us_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->contact_us_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_title'
                            ],
                            ['value' => $contact_us_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->contact_us_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_title'
                            ],
                            ['value' => $request->contact_us_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->contact_us_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_sub_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_sub_title'
                            ],
                            ['value' => $contact_us_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->contact_us_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $contact_us_sub_title->id,
                                'locale' => $key,
                                'key' => 'contact_us_sub_title'
                            ],
                            ['value' => $request->contact_us_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('business_settings')->updateOrInsert(['key' => 'opening_time'], [
                'value' => $request['opening_time']
            ]);

            DB::table('business_settings')->updateOrInsert(['key' => 'closing_time'], [
                'value' => $request['closing_time']
            ]);

            if ($request->opening_day == $request->closing_day) {
                Toastr::error(translate('messages.the_start_day_and_end_day_is_same'));
            } else {
                DB::table('business_settings')->updateOrInsert(['key' => 'opening_day'], [
                    'value' => $request['opening_day']
                ]);

                DB::table('business_settings')->updateOrInsert(['key' => 'closing_day'], [
                    'value' => $request['closing_day']
                ]);
            }


            Toastr::success(translate('messages.contact_section_updated'));
        } elseif ($tab == 'background-color') {
            DB::table('business_settings')->updateOrInsert(['key' => 'backgroundChange'], [
                'value' => json_encode([
                    'primary_1_hex' => $request['header-bg'],
                    'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                    'primary_2_hex' => $request['footer-bg'],
                    'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                ])
            ]);
            Toastr::success(translate('messages.background_updated'));
        }
        return back();
    }


    public function promotional_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this banner!');
            return back();
        }
        $banner = AdminPromotionalBanner::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success(translate('messages.banner_status_updated'));
        return back();
    }


    public function delivery_faq_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this banner!');
            return back();
        }
        $banner = DeliveryFaq::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success('Status Updated');
        return back();
    }

    public function delivery_faq_edit($id)
    {
        $delivery_faq = DeliveryFaq::findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.delivery-faq-section-edit', compact('delivery_faq'));
    }

    public function delivery_faq_update(Request $request , $id){
        $request->validate([
            'question' => 'required|max:100',
            'anwser' => 'required'
        ]);
        $delivery_faq = DeliveryFaq::find($id);
        $delivery_faq->question = $request->question[0];
        $delivery_faq->anwser = $request->anwser[0];
        $delivery_faq->save();



        Toastr::success(translate('Promotional Updated'));
        return back();
    }

    public function delevery_faq_destroy($id)
    {
        if (env('APP_MODE') == 'demo' && $id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_banner_please_add_a_new_banner_to_delete'));
            return back();
        }

        $delivery = DeliveryFaq::findOrFail($id);
        $delivery->delete();

        Toastr::success('Deleted Successfully');
        return back();
    }

    public function delivery_promotional_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this banner!');
            return back();
        }
        $banner = DeliveryPromotion::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success('Status Updated');
        return back();
    }

    public function delivery_promotional_edit($id)
    {
        $delivery = DeliveryPromotion::withoutGlobalScope('translate')->findOrFail($id);
        $delivery_heading = DeliveryPromotion::whereNotNull('main_heading')->first();


        return view('admin-views.business-settings.landing-page-settings.delivery-promotional-section-edit', compact('delivery', 'delivery_heading'));
    }

    public function delivery_promotional_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'sub_title' => 'required'
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }
        $delivery_heading = DeliveryPromotion::whereNotNull('main_heading')->first();
        $delivery = DeliveryPromotion::find($id);
        $delivery_heading->main_heading = $request->main_title[0];
        $delivery->title = $request->title[0];
        $delivery->subtitle = $request->sub_title[0];
        $delivery->image = $request->has('image') ? Helpers::update('delivery_promotion/', $delivery->image, 'png', $request->file('image')) : $delivery->image;
        $delivery->save();
        $delivery_heading->save();


        Toastr::success(translate('Promotional Updated'));
        return back();
    }

    public function delevery_promotional_destroy($id)
    {
        if (env('APP_MODE') == 'demo' && $id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_banner_please_add_a_new_banner_to_delete'));
            return back();
        }

        $delivery = DeliveryPromotion::findOrFail($id);
        $delivery->delete();

        Toastr::success('Deleted Successfully');
        return back();
    }


    public function promotional_edit($id)
    {
        $banner = AdminPromotionalBanner::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.admin-promotional-section-edit', compact('banner'));
    }



    public function promotional_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'sub_title' => 'required'
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }
        $banner = AdminPromotionalBanner::find($id);
        $banner->title = $request->title[array_search('default', $request->lang)];
        $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
        $banner->image = $request->has('image') ? Helpers::update('promotional_banner/', $banner->image, 'png', $request->file('image')) : $banner->image;
        $banner->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                            'translationable_id'    => $banner->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $banner->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                            'translationable_id'    => $banner->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->sub_title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                            'translationable_id'    => $banner->id,
                            'locale'                => $key,
                            'key'                   => 'sub_title'
                        ],
                        ['value'                 => $banner->sub_title]
                    );
                }
            } else {

                if ($request->sub_title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminPromotionalBanner',
                            'translationable_id'    => $banner->id,
                            'locale'                => $key,
                            'key'                   => 'sub_title'
                        ],
                        ['value'                 => $request->sub_title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('messages.banner_updated_successfully'));
        return back();
    }

    public function promotional_destroy(AdminPromotionalBanner $banner)
    {
        if (env('APP_MODE') == 'demo' && $banner->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_banner_please_add_a_new_banner_to_delete'));
            return back();
        }
        $banner->delete();
        Toastr::success(translate('messages.banner_deleted_successfully'));
        return back();
    }


    public function vendor_promotional_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this banner!');
            return back();
        }
        $banner = AdminPromotionalBanner::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success(translate('messages.banner_status_updated'));
        return back();
    }

    public function feature_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this feature!');
            return back();
        }
        $feature = AdminFeature::findOrFail($request->id);
        $feature->status = $request->status;
        $feature->save();
        Toastr::success(translate('messages.feature_status_updated'));
        return back();
    }

    public function feature_edit($id)
    {
        $feature = AdminFeature::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.admin-feature-list-edit', compact('feature'));
    }
    public function feature_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'sub_title' => 'required'
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }
        $feature = AdminFeature::find($id);
        $feature->title = $request->title[array_search('default', $request->lang)];
        $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
        $feature->image = $request->has('image') ? Helpers::update('admin_feature/', $feature->image, 'png', $request->file('image')) : $feature->image;
        $feature->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminFeature',
                            'translationable_id'    => $feature->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $feature->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminFeature',
                            'translationable_id'    => $feature->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->sub_title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminFeature',
                            'translationable_id'    => $feature->id,
                            'locale'                => $key,
                            'key'                   => 'sub_title'
                        ],
                        ['value'                 => $feature->sub_title]
                    );
                }
            } else {

                if ($request->sub_title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminFeature',
                            'translationable_id'    => $feature->id,
                            'locale'                => $key,
                            'key'                   => 'sub_title'
                        ],
                        ['value'                 => $request->sub_title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('messages.feature_updated_successfully'));
        return back();
    }

    public function feature_destroy(AdminFeature $feature)
    {
        if (env('APP_MODE') == 'demo' && $feature->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_feature_please_add_a_new_feature_to_delete'));
            return back();
        }
        $feature->delete();
        Toastr::success(translate('messages.feature_deleted_successfully'));
        return back();
    }

    public function criteria_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this criteria!');
            return back();
        }
        $criteria = AdminSpecialCriteria::findOrFail($request->id);
        $criteria->status = $request->status;
        $criteria->save();
        Toastr::success(translate('messages.criteria_status_updated'));
        return back();
    }

    public function criteria_edit($id)
    {
        $criteria = AdminSpecialCriteria::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.admin-landing-why-choose-edit', compact('criteria'));
    }
    public function criteria_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }
        $criteria = AdminSpecialCriteria::find($id);
        $criteria->title = $request->title[array_search('default', $request->lang)];
        $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
        $criteria->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                            'translationable_id'    => $criteria->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $criteria->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\AdminSpecialCriteria',
                            'translationable_id'    => $criteria->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('messages.criteria_updated_successfully'));
        return back();
    }

    public function criteria_destroy(AdminSpecialCriteria $criteria)
    {
        if (env('APP_MODE') == 'demo' && $criteria->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_criteria_please_add_a_new_criteria_to_delete'));
            return back();
        }
        $criteria->delete();
        Toastr::success(translate('messages.criteria_deleted_successfully'));
        return back();
    }

    public function review_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this review!');
            return back();
        }
        $review = AdminTestimonial::findOrFail($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.review_status_updated'));
        return back();
    }

    public function review_edit($id)
    {
        $review = AdminTestimonial::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.admin-landing-testimonial-test', compact('review'));
    }
    public function review_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'designation' => 'required',
            'review' => 'required',
        ]);

        $review = AdminTestimonial::findOrFail($id);
        $review->name = $request->name;
        $review->designation = $request->designation;
        $review->review = $request->review;
        $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
        $review->company_image = $request->has('company_image') ? Helpers::update('reviewer_company_image/', $review->company_image, 'png', $request->file('company_image')) : $review->company_image;
        $review->save();

        Toastr::success(translate('messages.review_updated_successfully'));
        return back();
    }

    public function review_destroy(AdminTestimonial $review)
    {
        if (env('APP_MODE') == 'demo' && $review->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_review_please_add_a_new_review_to_delete'));
            return back();
        }
        $review->delete();
        Toastr::success(translate('messages.review_deleted_successfully'));
        return back();
    }

    public function react_landing_page_settings($tab)
    {
        if ($tab == 'header') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-page-header');
        } else if ($tab == 'company-intro') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-page-company');
        } else if ($tab == 'download-user-app') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-download-apps');
        } else if ($tab == 'promotion-banner') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-promotion-banners');
        } else if ($tab == 'earn-money') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-earn-money');
        } else if ($tab == 'business-section') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-business');
        } else if ($tab == 'testimonials') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-testimonial');
        } else if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.react-landing-fixed-data');
        }
    }

    public function update_react_landing_page_settings(Request $request, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'download-app-section') {

            $request->validate([
                'download_user_app_title.0' => 'required',
                'download_user_app_sub_title.0' => 'required',
            ], [
                'download_user_app_title.0.required' => translate('messages.Default_title_is_required'),
                'download_user_app_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
            ]);

            $download_user_app_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_title')->first();

            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting();
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'react_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting();
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'react_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            // $download_user_app_image = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_image')->first();
            // if ($download_user_app_image == null) {
            //     $download_user_app_image = new DataSetting();
            // }
            // $download_user_app_image->key = 'download_user_app_image';
            // $download_user_app_image->type = 'react_landing_page';
            // $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            // $download_user_app_image->save();

            $banner_image_download = DataSetting::where('type', 'react_landing_page')->where('key', 'banner_image_download')->first();
            if ($banner_image_download == null) {
                $banner_image_download = new DataSetting();
            }
            $banner_image_download->key = 'banner_image_download';
            $banner_image_download->type = 'react_landing_page';
            $banner_image_download->value = $request->has('banner_image_download') ? Helpers::update('promotion_banner/', $banner_image_download->value, 'png', $request->file('banner_image_download')) : $banner_image_download->value;
            $banner_image_download->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'download_user_app_links', 'type' => 'react_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);


            Toastr::success(translate('messages.download_app_section_updated'));
        } elseif ($tab == 'earning-title') {
            $earning_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_title')->first();
            if ($earning_title == null) {
                $earning_title = new DataSetting();
            }

            $earning_title->key = 'earning_title';
            $earning_title->type = 'react_landing_page';
            $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
            $earning_title->save();

            $earning_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_sub_title')->first();
            if ($earning_sub_title == null) {
                $earning_sub_title = new DataSetting();
            }

            $earning_sub_title->key = 'earning_sub_title';
            $earning_sub_title->type = 'react_landing_page';
            $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
            $earning_sub_title->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title'
                            ],
                            ['value' => $request->earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title'
                            ],
                            ['value' => $request->earning_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.earning_section_updated'));
        } elseif ($tab == 'earning-seller-link') {
            $earning_seller_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_title')->first();
            if ($earning_seller_title == null) {
                $earning_seller_title = new DataSetting();
            }

            $earning_seller_title->key = 'earning_seller_title';
            $earning_seller_title->type = 'react_landing_page';
            $earning_seller_title->value = $request->earning_seller_title[array_search('default', $request->lang)];
            $earning_seller_title->save();

            $earning_seller_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_sub_title')->first();
            if ($earning_seller_sub_title == null) {
                $earning_seller_sub_title = new DataSetting();
            }

            $earning_seller_sub_title->key = 'earning_seller_sub_title';
            $earning_seller_sub_title->type = 'react_landing_page';
            $earning_seller_sub_title->value = $request->earning_seller_sub_title[array_search('default', $request->lang)];
            $earning_seller_sub_title->save();

            $earning_seller_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_button_name')->first();
            if ($earning_seller_button_name == null) {
                $earning_seller_button_name = new DataSetting();
            }

            $earning_seller_button_name->key = 'earning_seller_button_name';
            $earning_seller_button_name->type = 'react_landing_page';
            $earning_seller_button_name->value = $request->earning_seller_button_name[array_search('default', $request->lang)];
            $earning_seller_button_name->save();

            $earning_seller_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_button_url')->first();
            if ($earning_seller_button_url == null) {
                $earning_seller_button_url = new DataSetting();
            }

            $earning_seller_button_url->key = 'earning_seller_button_url';
            $earning_seller_button_url->type = 'react_landing_page';
            $earning_seller_button_url->value = $request->earning_seller_button_url;
            $earning_seller_button_url->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_seller_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_title->id,
                                'locale' => $key,
                                'key' => 'earning_seller_title'
                            ],
                            ['value' => $earning_seller_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_seller_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_title->id,
                                'locale' => $key,
                                'key' => 'earning_seller_title'
                            ],
                            ['value' => $request->earning_seller_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_seller_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_seller_sub_title'
                            ],
                            ['value' => $earning_seller_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_seller_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_seller_sub_title'
                            ],
                            ['value' => $request->earning_seller_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_seller_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'earning_seller_button_name'
                            ],
                            ['value' => $earning_seller_button_name?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_seller_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'earning_seller_button_name'
                            ],
                            ['value' => $request->earning_seller_button_name[$index]]
                        );
                    }
                }
            }
            Toastr::success(translate('messages.seller_links_updated'));
        } elseif ($tab == 'earning-dm-link') {
            // dd($request->all());
            $earning_dm_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_title')->first();
            if ($earning_dm_title == null) {
                $earning_dm_title = new DataSetting();
            }

            $earning_dm_title->key = 'earning_dm_title';
            $earning_dm_title->type = 'react_landing_page';
            $earning_dm_title->value = $request->earning_dm_title[array_search('default', $request->lang)];
            $earning_dm_title->save();

            $earning_dm_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_sub_title')->first();
            if ($earning_dm_sub_title == null) {
                $earning_dm_sub_title = new DataSetting();
            }

            $earning_dm_sub_title->key = 'earning_dm_sub_title';
            $earning_dm_sub_title->type = 'react_landing_page';
            $earning_dm_sub_title->value = $request->earning_dm_sub_title[array_search('default', $request->lang)];
            $earning_dm_sub_title->save();

            $earning_dm_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_button_name')->first();
            if ($earning_dm_button_name == null) {
                $earning_dm_button_name = new DataSetting();
            }

            $earning_dm_button_name->key = 'earning_dm_button_name';
            $earning_dm_button_name->type = 'react_landing_page';
            $earning_dm_button_name->value = $request->earning_dm_button_name[array_search('default', $request->lang)];
            $earning_dm_button_name->save();

            $earning_dm_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_button_url')->first();
            if ($earning_dm_button_url == null) {
                $earning_dm_button_url = new DataSetting();
            }

            $earning_dm_button_url->key = 'earning_dm_button_url';
            $earning_dm_button_url->type = 'react_landing_page';
            $earning_dm_button_url->value = $request->earning_dm_button_url;
            $earning_dm_button_url->save();


            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_dm_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_title->id,
                                'locale' => $key,
                                'key' => 'earning_dm_title'
                            ],
                            ['value' => $earning_dm_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_dm_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_title->id,
                                'locale' => $key,
                                'key' => 'earning_dm_title'
                            ],
                            ['value' => $request->earning_dm_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_dm_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_dm_sub_title'
                            ],
                            ['value' => $earning_dm_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_dm_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_dm_sub_title'
                            ],
                            ['value' => $request->earning_dm_sub_title[$index]]
                        );
                    }
                }

                if ($default_lang == $key && !($request->earning_dm_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_button_name->id,
                                'locale' => $key,
                                'key' => 'earning_dm_button_name'
                            ],
                            ['value' => $earning_dm_button_name?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_dm_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_dm_button_name->id,
                                'locale' => $key,
                                'key' => 'earning_dm_button_name'
                            ],
                            ['value' => $request->earning_dm_button_name[$index]]
                        );
                    }
                }
            }
            Toastr::success(translate('messages.delivery_man_links_updated'));
        } elseif ($tab == 'testimonial-title') {
            $testimonial_title = DataSetting::where('type', 'react_landing_page')->where('key', 'testimonial_title')->first();
            if ($testimonial_title == null) {
                $testimonial_title = new DataSetting();
            }

            $testimonial_title->key = 'testimonial_title';
            $testimonial_title->type = 'react_landing_page';
            $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
            $testimonial_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->testimonial_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $testimonial_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->testimonial_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title'
                            ],
                            ['value' => $request->testimonial_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.testimonial_section_updated'));
        } elseif ($tab == 'testimonial-list') {
            $request->validate([
                'name' => 'required',
                'designation' => 'required',
                'review' => 'required',
                'reviewer_image' => 'required'
            ]);

            $testimonial = new ReactTestimonial();
            $testimonial->name = $request->name;
            $testimonial->designation = $request->designation;
            $testimonial->review = $request->review;
            $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
            $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
            $testimonial->save();
            Toastr::success(translate('messages.testimonial_added_successfully'));
        } elseif ($tab == 'business-section') {
            $business_title = DataSetting::where('type', 'react_landing_page')->where('key', 'business_title')->first();
            if ($business_title == null) {
                $business_title = new DataSetting();
            }

            $business_title->key = 'business_title';
            $business_title->type = 'react_landing_page';
            $business_title->value = $request->business_title[array_search('default', $request->lang)];
            $business_title->save();

            $business_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'business_sub_title')->first();
            if ($business_sub_title == null) {
                $business_sub_title = new DataSetting();
            }

            $business_sub_title->key = 'business_sub_title';
            $business_sub_title->type = 'react_landing_page';
            $business_sub_title->value = $request->business_sub_title[array_search('default', $request->lang)];
            $business_sub_title->save();

            $business_image = DataSetting::where('type', 'react_landing_page')->where('key', 'business_image')->first();
            if ($business_image == null) {
                $business_image = new DataSetting();
            }
            $business_image->key = 'business_image';
            $business_image->type = 'react_landing_page';
            $business_image->value = $request->has('image') ? Helpers::update('business_image/', $business_image->value, 'png', $request->file('image')) : $business_image->value;
            $business_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->business_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $business_title->id,
                                'locale' => $key,
                                'key' => 'business_title'
                            ],
                            ['value' => $business_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->business_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $business_title->id,
                                'locale' => $key,
                                'key' => 'business_title'
                            ],
                            ['value' => $request->business_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->business_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $business_sub_title->id,
                                'locale' => $key,
                                'key' => 'business_sub_title'
                            ],
                            ['value' => $business_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->business_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $business_sub_title->id,
                                'locale' => $key,
                                'key' => 'business_sub_title'
                            ],
                            ['value' => $request->business_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'download_business_app_links', 'type' => 'react_landing_page'], [
                'value' => json_encode([
                    'seller_playstore_url_status' => $request['seller_playstore_url_status'],
                    'seller_playstore_url' => $request['seller_playstore_url'],
                    'seller_appstore_url_status' => $request['seller_appstore_url_status'],
                    'seller_appstore_url' => $request['seller_appstore_url'],
                    'dm_playstore_url_status' => $request['dm_playstore_url_status'],
                    'dm_playstore_url' => $request['dm_playstore_url'],
                    'dm_appstore_url_status' => $request['dm_appstore_url_status'],
                    'dm_appstore_url' => $request['dm_appstore_url'],
                ])
            ]);


            Toastr::success(translate('messages.business_section_updated'));
        } elseif ($tab == 'header-section') {
            $request->validate([
                'header_title.0' => 'required',
                'header_sub_title.0' => 'required',
                'banner_image' => 'required',
            ], [
                'header_title.0.required' => translate('messages.Default_title_is_required'),
                'header_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
                'banner_image.required' => translate('messages.Banner_image_is_required'),
            ]);
            $header_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_title')->first();
            if ($header_title == null) {
                $header_title = new DataSetting();
            }

            $header_title->key = 'header_title';
            $header_title->type = 'react_landing_page';
            $header_title->value = $request->header_title[array_search('default', $request->lang)];
            $header_title->save();

            $header_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_sub_title')->first();
            if ($header_sub_title == null) {
                $header_sub_title = new DataSetting();
            }

            $header_sub_title->key = 'header_sub_title';
            $header_sub_title->type = 'react_landing_page';
            $header_sub_title->value = $request->header_sub_title[array_search('default', $request->lang)];
            $header_sub_title->save();

            $header_tag_line = DataSetting::where('type', 'react_landing_page')->where('key', 'header_tag_line')->first();
            if ($header_tag_line == null) {
                $header_tag_line = new DataSetting();
            }

            $header_tag_line->key = 'header_tag_line';
            $header_tag_line->type = 'react_landing_page';
            $header_tag_line->value = $request->header_tag_line[array_search('default', $request->lang)];
            $header_tag_line->save();

            $header_icon = DataSetting::where('type', 'react_landing_page')->where('key', 'header_icon')->first();
            if ($header_icon == null) {
                $header_icon = new DataSetting();
            }
            $header_icon->key = 'header_icon';
            $header_icon->type = 'react_landing_page';
            $header_icon->value = $request->has('image') ? Helpers::update('header_icon/', $header_icon->value, 'png', $request->file('image')) : $header_icon->value;
            $header_icon->save();

            $header_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'header_banner')->first();
            if ($header_banner == null) {
                $header_banner = new DataSetting();
            }
            $header_banner->key = 'header_banner';
            $header_banner->type = 'react_landing_page';
            $header_banner->value = $request->has('banner_image') ? Helpers::update('header_banner/', $header_banner->value, 'png', $request->file('banner_image')) : $header_banner->value;
            $header_banner->save();

            $default_lang = str_replace('_', '-', app()->getLocale());

            foreach ($request->lang as $index => $key) {

                if ($default_lang == $key && !($request->header_title[$index])) {

                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_title->id,
                                'locale' => $key,
                                'key' => 'header_title'
                            ],
                            ['value' => $header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_title->id,
                                'locale' => $key,
                                'key' => 'header_title'
                            ],
                            ['value' => $request->header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_sub_title->id,
                                'locale' => $key,
                                'key' => 'header_sub_title'
                            ],
                            ['value' => $header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_sub_title->id,
                                'locale' => $key,
                                'key' => 'header_sub_title'
                            ],
                            ['value' => $request->header_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->header_tag_line[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_tag_line->id,
                                'locale' => $key,
                                'key' => 'header_tag_line'
                            ],
                            ['value' => $header_tag_line->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_tag_line[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_tag_line->id,
                                'locale' => $key,
                                'key' => 'header_tag_line'
                            ],
                            ['value' => $request->header_tag_line[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.header_section_updated'));
        } elseif ($tab == 'company-section') {


            $request->validate([
                'company_title.0' => 'required',
                'company_sub_title.0' => 'required',
                'company_button_url' => 'required_unless:company_button_name.0,!=,null',
                'company_button_name.0' => 'required_unless:company_button_url,!=,null',
            ], [
                'company_title.0.required' => translate('messages.Default_title_is_required'),
                'company_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
                'company_button_name.0.required_unless' => translate('messages.Default_button_name_is_required'),
                'company_button_url.required_unless' => translate('messages.Button_redirec_url_is_required'),
            ]);

            $company_title = DataSetting::where('type', 'react_landing_page')->where('key', 'company_title')->first();
            if ($company_title == null) {
                $company_title = new DataSetting();
            }

            $company_title->key = 'company_title';
            $company_title->type = 'react_landing_page';
            $company_title->value = $request->company_title[array_search('default', $request->lang)];
            $company_title->save();

            $company_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'company_sub_title')->first();
            if ($company_sub_title == null) {
                $company_sub_title = new DataSetting();
            }

            $company_sub_title->key = 'company_sub_title';
            $company_sub_title->type = 'react_landing_page';
            $company_sub_title->value = $request->company_sub_title[array_search('default', $request->lang)];
            $company_sub_title->save();

            $company_description = DataSetting::where('type', 'react_landing_page')->where('key', 'company_description')->first();
            if ($company_description == null) {
                $company_description = new DataSetting();
            }

            $company_description->key = 'company_description';
            $company_description->type = 'react_landing_page';
            $company_description->value = $request->company_description[array_search('default', $request->lang)];
            $company_description->save();



           $banner_image_company = DataSetting::where('type', 'react_landing_page')->where('key', 'banner_image_company')->first();
            if ($banner_image_company == null) {
                $banner_image_company = new DataSetting();
            }
            $banner_image_company->key = 'banner_image_company';
            $banner_image_company->type = 'react_landing_page';
            $banner_image_company->value = $request->has('banner_image_company') ? Helpers::update('promotion_banner/', $banner_image_company->value, 'png', $request->file('banner_image_company')) : $banner_image_company->value;
            $banner_image_company->save();

            $company_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'company_button_name')->first();
            if ($company_button_name == null) {
                $company_button_name = new DataSetting();
            }

            $company_button_name->key = 'company_button_name';
            $company_button_name->type = 'react_landing_page';
            $company_button_name->value = $request->company_button_name[array_search('default', $request->lang)];
            $company_button_name->save();

            $company_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'company_button_url')->first();
            if ($company_button_url == null) {
                $company_button_url = new DataSetting();
            }

            $company_button_url->key = 'company_button_url';
            $company_button_url->type = 'react_landing_page';
            $company_button_url->value = $request->company_button_url;
            $company_button_url->save();

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->company_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_title->id,
                                'locale' => $key,
                                'key' => 'company_title'
                            ],
                            ['value' => $company_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->company_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_title->id,
                                'locale' => $key,
                                'key' => 'company_title'
                            ],
                            ['value' => $request->company_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->company_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_sub_title->id,
                                'locale' => $key,
                                'key' => 'company_sub_title'
                            ],
                            ['value' => $company_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->company_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_sub_title->id,
                                'locale' => $key,
                                'key' => 'company_sub_title'
                            ],
                            ['value' => $request->company_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->company_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_description->id,
                                'locale' => $key,
                                'key' => 'company_description'
                            ],
                            ['value' => $company_description->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->company_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_description->id,
                                'locale' => $key,
                                'key' => 'company_description'
                            ],
                            ['value' => $request->company_description[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->company_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_button_name->id,
                                'locale' => $key,
                                'key' => 'company_button_name'
                            ],
                            ['value' => $company_button_name->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->company_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $company_button_name->id,
                                'locale' => $key,
                                'key' => 'company_button_name'
                            ],
                            ['value' => $request->company_button_name[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.company_section_updated'));
        } else if ($tab == 'promotion-banner') {
            $data = [];
            $imageName = null;
            $promotion_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'promotion_banner')->first();
            if ($promotion_banner) {
                $data = json_decode($promotion_banner->value, true);
            }
            if (count($data) >= 6) {
                Toastr::error(translate('messages.you_have_already_added_maximum_banner_image'));
                return back();
            }
            if ($request->has('image')) {
                $imageName = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
            }
            array_push($data, [
                'img' => $imageName,
                // 'title' => $request->title,
                // 'sub_title' => $request->sub_title,
            ]);

            DB::table('data_settings')->updateOrInsert(['key' => 'promotion_banner', 'type' => 'react_landing_page'], [
                'value' => json_encode($data),
            ]);
            Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
        } else if ($tab == 'fixed-banner') {
            $fixed_promotional_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_promotional_banner')->first();
            if ($fixed_promotional_banner == null) {
                $fixed_promotional_banner = new DataSetting();
            }
            $fixed_promotional_banner->key = 'fixed_promotional_banner';
            $fixed_promotional_banner->type = 'react_landing_page';
            $fixed_promotional_banner->value = $request->has('fixed_promotional_banner') ? Helpers::update('promotional_banner/', $fixed_promotional_banner->value, 'png', $request->file('fixed_promotional_banner')) : $fixed_promotional_banner->value;
            $fixed_promotional_banner->save();
            Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
        } else if ($tab == 'fixed-newsletter') {


            $request->validate([
                'fixed_newsletter_title.0' => 'required',
                'fixed_newsletter_sub_title.0' => 'required',
            ], [
                'fixed_newsletter_title.0.required' => translate('messages.Default_title_is_required'),
                'fixed_newsletter_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),

            ]);


            $fixed_newsletter_title = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_newsletter_title')->first();
            if ($fixed_newsletter_title == null) {
                $fixed_newsletter_title = new DataSetting();
            }

            $fixed_newsletter_title->key = 'fixed_newsletter_title';
            $fixed_newsletter_title->type = 'react_landing_page';
            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
            $fixed_newsletter_title->save();

            $fixed_newsletter_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
            if ($fixed_newsletter_sub_title == null) {
                $fixed_newsletter_sub_title = new DataSetting();
            }

            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
            $fixed_newsletter_sub_title->type = 'react_landing_page';
            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
            $fixed_newsletter_sub_title->save();

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title'
                            ],
                            ['value' => $request->fixed_newsletter_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title'
                            ],
                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.landing_page_newsletter_content_updated'));
        } else if ($tab == 'fixed-footer') {
            $fixed_footer_description = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_footer_description')->first();
            if ($fixed_footer_description == null) {
                $fixed_footer_description = new DataSetting();
            }

            $fixed_footer_description->key = 'fixed_footer_description';
            $fixed_footer_description->type = 'react_landing_page';
            $fixed_footer_description->value = $request->fixed_footer_description[array_search('default', $request->lang)];
            $fixed_footer_description->save();

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_footer_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_description->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_description'
                            ],
                            ['value' => $fixed_footer_description?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_footer_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_description->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_description'
                            ],
                            ['value' => $request->fixed_footer_description[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.landing_page_footer_content_updated'));
        }
        return back();
    }

    public function delete_react_landing_page_settings($tab, $key)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        $item = DataSetting::where('type', 'react_landing_page')->where('key', $tab)->first();
        $data = $item ? json_decode($item->value, true) : null;
        if ($data && array_key_exists($key, $data)) {
            if (isset($data[$key]['img']) && Storage::disk('public')->exists('promotion_banner/' . $data[$key]['img'])) {
                Storage::disk('public')->delete('promotion_banner/' . $data[$key]['img']);
            }
            array_splice($data, $key, 1);

            $item->value = json_encode($data);
            $item->save();
            Toastr::success(translate('messages.' . $tab) . ' ' . translate('messages.deleted'));
            return back();
        }
        Toastr::error(translate('messages.not_found'));
        return back();
    }

    public function review_react_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this review!');
            return back();
        }
        $review = ReactTestimonial::findOrFail($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.review_status_updated'));
        return back();
    }

    public function review_react_edit($id)
    {
        $review = ReactTestimonial::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.react-landing-testimonial-edit', compact('review'));
    }
    public function review_react_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'designation' => 'required',
            'review' => 'required',
        ]);

        $review = ReactTestimonial::findOrFail($id);
        $review->name = $request->name;
        $review->designation = $request->designation;
        $review->review = $request->review;
        $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
        $review->company_image = $request->has('company_image') ? Helpers::update('reviewer_company_image/', $review->company_image, 'png', $request->file('company_image')) : $review->company_image;
        $review->save();

        Toastr::success(translate('messages.review_updated_successfully'));
        return back();
    }

    public function review_react_destroy(ReactTestimonial $review)
    {
        if (env('APP_MODE') == 'demo' && $review->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_review_please_add_a_new_review_to_delete'));
            return back();
        }
        $review->delete();
        Toastr::success(translate('messages.review_deleted_successfully'));
        return back();
    }

    public function flutter_landing_page_settings($tab)
    {
        if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.flutter-fixed-data');
        } else if ($tab == 'special-criteria') {
            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria');
        } else if ($tab == 'join-as') {
            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-join-as');
        } else if ($tab == 'download-apps') {
            return view('admin-views.business-settings.landing-page-settings.flutter-download-apps');
        }
    }

    public function update_flutter_landing_page_settings(Request $request, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'special-criteria-list') {
            $request->validate([
                'title' => 'required',
                'image' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('default_data_is_required'));
                return back();
            }
            $criteria = new FlutterSpecialCriteria();
            $criteria->title = $request->title[array_search('default', $request->lang)];
            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
            $criteria->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\FlutterSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $criteria->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type'  => 'App\Models\FlutterSpecialCriteria',
                                'translationable_id'    => $criteria->id,
                                'locale'                => $key,
                                'key'                   => 'title'
                            ],
                            ['value'                 => $request->title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.criteria_added_successfully'));
        } elseif ($tab == 'download-app-section') {
            $download_user_app_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting();
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'flutter_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting();
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'flutter_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            $download_user_app_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_image')->first();
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting();
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'flutter_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title'
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title'
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            DB::table('data_settings')->updateOrInsert(['key' => 'download_user_app_links', 'type' => 'flutter_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url']
                ])
            ]);


            Toastr::success(translate('messages.download_app_section_updated'));
        } elseif ($tab == 'fixed-header') {

            $fixed_header_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_title')->first();
            if ($fixed_header_title == null) {
                $fixed_header_title = new DataSetting();
            }

            $fixed_header_title->key = 'fixed_header_title';
            $fixed_header_title->type = 'flutter_landing_page';
            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
            $fixed_header_title->save();

            $fixed_header_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_sub_title')->first();
            if ($fixed_header_sub_title == null) {
                $fixed_header_sub_title = new DataSetting();
            }

            $fixed_header_sub_title->key = 'fixed_header_sub_title';
            $fixed_header_sub_title->type = 'flutter_landing_page';
            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
            $fixed_header_sub_title->save();

            $fixed_header_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_image')->first();
            if ($fixed_header_image == null) {
                $fixed_header_image = new DataSetting();
            }
            $fixed_header_image->key = 'fixed_header_image';
            $fixed_header_image->type = 'flutter_landing_page';
            $fixed_header_image->value = $request->has('image') ? Helpers::update('fixed_header_image/', $fixed_header_image->value, 'png', $request->file('image')) : $fixed_header_image->value;
            $fixed_header_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title'
                            ],
                            ['value' => $request->fixed_header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title'
                            ],
                            ['value' => $request->fixed_header_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.landing_page_header_updated'));
        } elseif ($tab == 'fixed-location') {

            $fixed_location_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_location_title')->first();
            if ($fixed_location_title == null) {
                $fixed_location_title = new DataSetting();
            }

            $fixed_location_title->key = 'fixed_location_title';
            $fixed_location_title->type = 'flutter_landing_page';
            $fixed_location_title->value = $request->fixed_location_title[array_search('default', $request->lang)];
            $fixed_location_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_location_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_location_title->id,
                                'locale' => $key,
                                'key' => 'fixed_location_title'
                            ],
                            ['value' => $fixed_location_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_location_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_location_title->id,
                                'locale' => $key,
                                'key' => 'fixed_location_title'
                            ],
                            ['value' => $request->fixed_location_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.landing_page_location_title_updated'));
        } elseif ($tab == 'fixed-module') {

            $fixed_module_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_title')->first();
            if ($fixed_module_title == null) {
                $fixed_module_title = new DataSetting();
            }

            $fixed_module_title->key = 'fixed_module_title';
            $fixed_module_title->type = 'flutter_landing_page';
            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
            $fixed_module_title->save();

            $fixed_module_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_sub_title')->first();
            if ($fixed_module_sub_title == null) {
                $fixed_module_sub_title = new DataSetting();
            }

            $fixed_module_sub_title->key = 'fixed_module_sub_title';
            $fixed_module_sub_title->type = 'flutter_landing_page';
            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
            $fixed_module_sub_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title'
                            ],
                            ['value' => $request->fixed_module_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title'
                            ],
                            ['value' => $request->fixed_module_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.landing_page_module_updated'));
        } elseif ($tab == 'join-seller') {
            $join_seller_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_title')->first();
            if ($join_seller_title == null) {
                $join_seller_title = new DataSetting();
            }

            $join_seller_title->key = 'join_seller_title';
            $join_seller_title->type = 'flutter_landing_page';
            $join_seller_title->value = $request->join_seller_title[array_search('default', $request->lang)];
            $join_seller_title->save();

            $join_seller_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_sub_title')->first();
            if ($join_seller_sub_title == null) {
                $join_seller_sub_title = new DataSetting();
            }

            $join_seller_sub_title->key = 'join_seller_sub_title';
            $join_seller_sub_title->type = 'flutter_landing_page';
            $join_seller_sub_title->value = $request->join_seller_sub_title[array_search('default', $request->lang)];
            $join_seller_sub_title->save();

            $join_seller_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_name')->first();
            if ($join_seller_button_name == null) {
                $join_seller_button_name = new DataSetting();
            }

            $join_seller_button_name->key = 'join_seller_button_name';
            $join_seller_button_name->type = 'flutter_landing_page';
            $join_seller_button_name->value = $request->join_seller_button_name[array_search('default', $request->lang)];
            $join_seller_button_name->save();

            $join_seller_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_url')->first();
            if ($join_seller_button_url == null) {
                $join_seller_button_url = new DataSetting();
            }

            $join_seller_button_url->key = 'join_seller_button_url';
            $join_seller_button_url->type = 'flutter_landing_page';
            $join_seller_button_url->value = $request->join_seller_button_url;
            $join_seller_button_url->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->join_seller_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_title'
                            ],
                            ['value' => $join_seller_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_title'
                            ],
                            ['value' => $request->join_seller_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_seller_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_sub_title'
                            ],
                            ['value' => $join_seller_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_sub_title'
                            ],
                            ['value' => $request->join_seller_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_seller_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'join_seller_button_name'
                            ],
                            ['value' => $join_seller_button_name->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'join_seller_button_name'
                            ],
                            ['value' => $request->join_seller_button_name[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.join_as_seller_data_updated'));
        } elseif ($tab == 'join-delivery') {
            $join_delivery_man_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_title')->first();
            if ($join_delivery_man_title == null) {
                $join_delivery_man_title = new DataSetting();
            }

            $join_delivery_man_title->key = 'join_delivery_man_title';
            $join_delivery_man_title->type = 'flutter_landing_page';
            $join_delivery_man_title->value = $request->join_delivery_man_title[array_search('default', $request->lang)];
            $join_delivery_man_title->save();

            $join_delivery_man_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_sub_title')->first();
            if ($join_delivery_man_sub_title == null) {
                $join_delivery_man_sub_title = new DataSetting();
            }

            $join_delivery_man_sub_title->key = 'join_delivery_man_sub_title';
            $join_delivery_man_sub_title->type = 'flutter_landing_page';
            $join_delivery_man_sub_title->value = $request->join_delivery_man_sub_title[array_search('default', $request->lang)];
            $join_delivery_man_sub_title->save();

            $join_delivery_man_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_name')->first();
            if ($join_delivery_man_button_name == null) {
                $join_delivery_man_button_name = new DataSetting();
            }

            $join_delivery_man_button_name->key = 'join_delivery_man_button_name';
            $join_delivery_man_button_name->type = 'flutter_landing_page';
            $join_delivery_man_button_name->value = $request->join_delivery_man_button_name[array_search('default', $request->lang)];
            $join_delivery_man_button_name->save();

            $join_delivery_man_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_url')->first();
            if ($join_delivery_man_button_url == null) {
                $join_delivery_man_button_url = new DataSetting();
            }

            $join_delivery_man_button_url->key = 'join_delivery_man_button_url';
            $join_delivery_man_button_url->type = 'flutter_landing_page';
            $join_delivery_man_button_url->value = $request->join_delivery_man_button_url;
            $join_delivery_man_button_url->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->join_delivery_man_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_title'
                            ],
                            ['value' => $join_delivery_man_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_title'
                            ],
                            ['value' => $request->join_delivery_man_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_delivery_man_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_sub_title'
                            ],
                            ['value' => $join_delivery_man_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_sub_title'
                            ],
                            ['value' => $request->join_delivery_man_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_delivery_man_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_button_name->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_button_name'
                            ],
                            ['value' => $join_delivery_man_button_name->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_button_name->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_button_name'
                            ],
                            ['value' => $request->join_delivery_man_button_name[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.join_as_delivery_man_data_updated'));
        }
        return back();
    }

    public function flutter_criteria_status(Request $request)
    {
        if (env('APP_MODE') == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this criteria!');
            return back();
        }
        $criteria = FlutterSpecialCriteria::findOrFail($request->id);
        $criteria->status = $request->status;
        $criteria->save();
        Toastr::success(translate('messages.criteria_status_updated'));
        return back();
    }

    public function flutter_criteria_edit($id)
    {
        $criteria = FlutterSpecialCriteria::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria-edit', compact('criteria'));
    }
    public function flutter_criteria_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }
        $criteria = FlutterSpecialCriteria::find($id);
        $criteria->title = $request->title[array_search('default', $request->lang)];
        $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
        $criteria->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\FlutterSpecialCriteria',
                            'translationable_id'    => $criteria->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $criteria->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\FlutterSpecialCriteria',
                            'translationable_id'    => $criteria->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('messages.criteria_updated_successfully'));
        return back();
    }

    public function flutter_criteria_destroy(FlutterSpecialCriteria $criteria)
    {
        if (env('APP_MODE') == 'demo' && $criteria->id == 1) {
            Toastr::warning(translate('messages.you_can_not_delete_this_criteria_please_add_a_new_criteria_to_delete'));
            return back();
        }
        $criteria->delete();
        Toastr::success(translate('messages.criteria_deleted_successfully'));
        return back();
    }

    public function email_index(Request $request, $type, $tab)
    {


        $template = $request->query('template', null);
        if ($tab == 'new-order') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.place-order-format', compact('template'));
        } else if ($tab == 'forgot-password') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.forgot-pass-format', compact('template'));
        } else if ($tab == 'store-registration') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.store-registration-format', compact('template'));
        } else if ($tab == 'dm-registration') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.dm-registration-format', compact('template'));
        } else if ($tab == 'registration') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.registration-format', compact('template'));
        } else if ($tab == 'approve') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.approve-format', compact('template'));
        } else if ($tab == 'deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.deny-format', compact('template'));
        } else if ($tab == 'withdraw-request') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-request-format', compact('template'));
        } else if ($tab == 'withdraw-approve') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-approve-format', compact('template'));
        } else if ($tab == 'withdraw-deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-deny-format', compact('template'));
        } else if ($tab == 'campaign-request') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-request-format', compact('template'));
        } else if ($tab == 'campaign-approve') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-approve-format', compact('template'));
        } else if ($tab == 'campaign-deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-deny-format', compact('template'));
        } else if ($tab == 'refund-request') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-request-format', compact('template'));
        } else if ($tab == 'login') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.login-format', compact('template'));
        } else if ($tab == 'suspend') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.suspend-format', compact('template'));
        } else if ($tab == 'cash-collect') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.cash-collect-format', compact('template'));
        } else if ($tab == 'registration-otp') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.registration-otp-format', compact('template'));
        } else if ($tab == 'login-otp') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.login-otp-format', compact('template'));
        } else if ($tab == 'order-verification') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.order-verification-format', compact('template'));
        } else if ($tab == 'refund-request-deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-request-deny-format', compact('template'));
        } else if ($tab == 'add-fund') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.add-fund-format', compact('template'));
        } else if ($tab == 'refund-order') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-order-format', compact('template'));
        } else if ($tab == 'product-approved') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.product-approved-format', compact('template'));
        } else if ($tab == 'product-deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.product-deny-format', compact('template'));
        } else if ($tab == 'offline-payment-approve') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.offline-approved-format', compact('template'));
        } else if ($tab == 'offline-payment-deny') {
            return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.offline-deny-format', compact('template'));
        }
    }

    public function update_email_index(Request $request, $type, $tab)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'new-order') {
            $email_type = 'new_order';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'new_order')->first();
        } elseif ($tab == 'forget-password') {
            $email_type = 'forget_password';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'forget_password')->first();
        } elseif ($tab == 'store-registration') {
            $email_type = 'store_registration';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'store_registration')->first();
        } elseif ($tab == 'dm-registration') {
            $email_type = 'dm_registration';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'dm_registration')->first();
        } elseif ($tab == 'registration') {
            $email_type = 'registration';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'registration')->first();
        } elseif ($tab == 'approve') {
            $email_type = 'approve';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'approve')->first();
        } elseif ($tab == 'deny') {
            $email_type = 'deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'deny')->first();
        } elseif ($tab == 'withdraw-request') {
            $email_type = 'withdraw_request';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_request')->first();
        } elseif ($tab == 'withdraw-approve') {
            $email_type = 'withdraw_approve';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_approve')->first();
        } elseif ($tab == 'withdraw-deny') {
            $email_type = 'withdraw_deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_deny')->first();
        } elseif ($tab == 'campaign-request') {
            $email_type = 'campaign_request';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_request')->first();
        } elseif ($tab == 'campaign-approve') {
            $email_type = 'campaign_approve';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_approve')->first();
        } elseif ($tab == 'campaign-deny') {
            $email_type = 'campaign_deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_deny')->first();
        } elseif ($tab == 'refund-request') {
            $email_type = 'refund_request';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_request')->first();
        } elseif ($tab == 'login') {
            $email_type = 'login';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'login')->first();
        } elseif ($tab == 'suspend') {
            $email_type = 'suspend';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'suspend')->first();
        } elseif ($tab == 'cash-collect') {
            $email_type = 'cash_collect';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'cash_collect')->first();
        } elseif ($tab == 'registration-otp') {
            $email_type = 'registration_otp';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'registration_otp')->first();
        } elseif ($tab == 'login-otp') {
            $email_type = 'login_otp';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'login_otp')->first();
        } elseif ($tab == 'order-verification') {
            $email_type = 'order_verification';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'order_verification')->first();
        } elseif ($tab == 'refund-request-deny') {
            $email_type = 'refund_request_deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_request_deny')->first();
        } elseif ($tab == 'add-fund') {
            $email_type = 'add_fund';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'add_fund')->first();
        } elseif ($tab == 'refund-order') {
            $email_type = 'refund_order';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_order')->first();
        } elseif ($tab == 'product-deny') {
            $email_type = 'product_deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'product_deny')->first();
        } elseif ($tab == 'product-approved') {
            $email_type = 'product_approved';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'product_approved')->first();
        } elseif ($tab == 'offline-payment-deny') {
            $email_type = 'offline_payment_deny';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'offline_payment_deny')->first();
        } elseif ($tab == 'offline-payment-approve') {
            $email_type = 'offline_payment_approve';
            $template = EmailTemplate::where('type', $type)->where('email_type', 'offline_payment_approve')->first();
        }

        if ($template == null) {
            $template = new EmailTemplate();
        }
        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('default_data_is_required'));
            return back();
        }

        $template->title = $request->title[array_search('default', $request->lang)];
        $template->body = $request->body[array_search('default', $request->lang)];
        $template->button_name = $request->button_name ? $request->button_name[array_search('default', $request->lang)] : '';
        $template->footer_text = $request->footer_text[array_search('default', $request->lang)];
        $template->copyright_text = $request->copyright_text[array_search('default', $request->lang)];
        $template->background_image = $request->has('background_image') ? Helpers::update('email_template/', $template->background_image, 'png', $request->file('background_image')) : $template->background_image;
        $template->image = $request->has('image') ? Helpers::update('email_template/', $template->image, 'png', $request->file('image')) : $template->image;
        $template->logo = $request->has('logo') ? Helpers::update('email_template/', $template->logo, 'png', $request->file('logo')) : $template->logo;
        $template->icon = $request->has('icon') ? Helpers::update('email_template/', $template->icon, 'png', $request->file('icon')) : $template->icon;
        $template->email_type = $email_type;
        $template->type = $type;
        $template->button_url = $request->button_url ?? '';
        $template->email_template = $request->email_template;
        $template->privacy = $request->privacy ? '1' : 0;
        $template->refund = $request->refund ? '1' : 0;
        $template->cancelation = $request->cancelation ? '1' : 0;
        $template->contact = $request->contact ? '1' : 0;
        $template->facebook = $request->facebook ? '1' : 0;
        $template->instagram = $request->instagram ? '1' : 0;
        $template->twitter = $request->twitter ? '1' : 0;
        $template->linkedin = $request->linkedin ? '1' : 0;
        $template->pinterest = $request->pinterest ? '1' : 0;
        $template->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[array_search('default', $request->lang)] ?? '']
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'title'
                        ],
                        ['value'                 => $request->title[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->body[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'body'
                        ],
                        ['value'                 => $request->body[array_search('default', $request->lang)] ?? '']
                    );
                }
            } else {
                if ($request->body[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'body'
                        ],
                        ['value'                 => $request->body[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->button_name && $request->button_name[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'button_name'
                        ],
                        ['value'                 => $request->button_name[array_search('default', $request->lang)] ?? '']
                    );
                }
            } else {

                if ($request->button_name && $request->button_name[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'button_name'
                        ],
                        ['value'                 => $request->button_name[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->footer_text[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'footer_text'
                        ],
                        ['value'                 => $request->footer_text[array_search('default', $request->lang)] ?? '']
                    );
                }
            } else {

                if ($request->footer_text[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'footer_text'
                        ],
                        ['value'                 => $request->footer_text[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->copyright_text[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'copyright_text'
                        ],
                        ['value'                 => $request->copyright_text[array_search('default', $request->lang)] ?? '']
                    );
                }
            } else {

                if ($request->copyright_text[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type'  => 'App\Models\EmailTemplate',
                            'translationable_id'    => $template->id,
                            'locale'                => $key,
                            'key'                   => 'copyright_text'
                        ],
                        ['value'                 => $request->copyright_text[$index]]
                    );
                }
            }
        }

        Toastr::success(translate('messages.template_added_successfully'));
        return back();
    }

    public function update_email_status(Request $request, $type, $tab, $status)
    {

        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        if ($tab == 'place-order') {
            DB::table('business_settings')->updateOrInsert(['key' => 'place_order_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'forgot-password') {
            DB::table('business_settings')->updateOrInsert(['key' => 'forget_password_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'store-registration') {
            DB::table('business_settings')->updateOrInsert(['key' => 'store_registration_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'dm-registration') {
            DB::table('business_settings')->updateOrInsert(['key' => 'dm_registration_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'registration') {
            DB::table('business_settings')->updateOrInsert(['key' => 'registration_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'approve') {
            DB::table('business_settings')->updateOrInsert(['key' => 'approve_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'withdraw-request') {
            DB::table('business_settings')->updateOrInsert(['key' => 'withdraw_request_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'withdraw-approve') {
            DB::table('business_settings')->updateOrInsert(['key' => 'withdraw_approve_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'withdraw-deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'withdraw_deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'campaign-request') {
            DB::table('business_settings')->updateOrInsert(['key' => 'campaign_request_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'campaign-approve') {
            DB::table('business_settings')->updateOrInsert(['key' => 'campaign_approve_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'campaign-deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'campaign_deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'refund-request') {
            DB::table('business_settings')->updateOrInsert(['key' => 'refund_request_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'login') {
            DB::table('business_settings')->updateOrInsert(['key' => 'login_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'suspend') {
            DB::table('business_settings')->updateOrInsert(['key' => 'suspend_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'cash-collect') {
            DB::table('business_settings')->updateOrInsert(['key' => 'cash_collect_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'registration-otp') {
            DB::table('business_settings')->updateOrInsert(['key' => 'registration_otp_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'login-otp') {
            DB::table('business_settings')->updateOrInsert(['key' => 'login_otp_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'order-verification') {
            DB::table('business_settings')->updateOrInsert(['key' => 'order_verification_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'refund-request-deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'refund_request_deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'add-fund') {
            DB::table('business_settings')->updateOrInsert(['key' => 'add_fund_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'refund-order') {
            DB::table('business_settings')->updateOrInsert(['key' => 'refund_order_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'product-deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'product_deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'product-approved') {
            DB::table('business_settings')->updateOrInsert(['key' => 'product_approve_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'offline-payment-deny') {
            DB::table('business_settings')->updateOrInsert(['key' => 'offline_payment_deny_mail_status_' . $type], [
                'value' => $status
            ]);
        } else if ($tab == 'offline-payment-approve') {
            DB::table('business_settings')->updateOrInsert(['key' => 'offline_payment_approve_mail_status_' . $type], [
                'value' => $status
            ]);
        }

        Toastr::success(translate('messages.email_status_updated'));
        return back();
    }

    public function login_url_page()
    {
        $data = array_column(DataSetting::whereIn('key', [
            'store_employee_login_url', 'store_login_url', 'admin_employee_login_url', 'admin_login_url'
        ])->get(['key', 'value'])->toArray(), 'value', 'key');

        return view('admin-views.login-setup.login_setup', compact('data'));
    }
    public function login_url_page_update(Request $request)
    {

        $request->validate([
            'type' => 'required',
            'admin_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'admin_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'store_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'store_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
        ]);

        if ($request->type == 'admin') {
            DataSetting::query()->updateOrInsert(['key' => 'admin_login_url', 'type' => 'login_admin'], [
                'value' => $request->admin_login_url
            ]);
        } elseif ($request->type == 'admin_employee') {
            DataSetting::query()->updateOrInsert(['key' => 'admin_employee_login_url', 'type' => 'login_admin_employee'], [
                'value' => $request->admin_employee_login_url
            ]);
        } elseif ($request->type == 'store') {
            DataSetting::query()->updateOrInsert(['key' => 'store_login_url', 'type' => 'login_store'], [
                'value' => $request->store_login_url
            ]);
        } elseif ($request->type == 'store_employee') {
            DataSetting::query()->updateOrInsert(['key' => 'store_employee_login_url', 'type' => 'login_store_employee'], [
                'value' => $request->store_employee_login_url
            ]);
        }
        Toastr::success(translate('messages.update_successfull'));
        return back();
    }

    public function remove_image(Request $request)
    {

        $request->validate([
            'model_name' => 'required',
            'id' => 'required',
            'image_path' => 'required',
            'field_name' => 'required',
        ]);
        try {

            $model_name = $request->model_name;
            $model = app("\\App\\Models\\{$model_name}");
            $data =  $model->where('id', $request->id)->first();

            $data_value = $data?->{$request->field_name};

            if ($request?->json == 1) {
                $data_value = json_decode($data?->value, true);
                if (Storage::disk('public')->exists($request->image_path . '/' . $data_value[$request->field_name])) {
                    Storage::disk('public')->delete($request->image_path . '/' . $data_value[$request->field_name]);
                }
                $data_value[$request->field_name] = null;
                $data->value = json_encode($data_value);
            } else {
                if (Storage::disk('public')->exists($request->image_path . '/' . $data_value)) {
                    Storage::disk('public')->delete($request->image_path . '/' . $data_value);
                }
                $data->{$request->field_name} = null;
            }

            $data?->save();
        } catch (\Throwable $th) {
            Toastr::error($th->getMessage() . 'Line....' . $th->getLine());
            return back();
        }
        Toastr::success(translate('messages.Image_removed_successfully'));
        return back();
    }

    public function react_setup()
    {

        Helpers::react_domain_status_check();
        return view('admin-views.business-settings.react-setup');
    }

    public function react_update(Request $request)
    {

        DB::table('business_settings')->updateOrInsert(['key' => 'react_setup'], [
            'value' => json_encode([
                'status' => 1,
                'react_license_code' => $request['react_license_code'],
                'react_domain' => $request['react_domain'],
                'react_platform' => 'iss'
            ])
        ]);



        Toastr::success(translate('messages.react_data_updated'));
        return back();

        Toastr::error(translate('messages.Invalid_license_code_or_unregistered_domain'));
        return back()->withInput(['invalid-data' => true]);
    }

    public function landing_page_settings_update(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'landing_integration_via' => 'required',
            'redirect_url' => 'required_if:landing_integration_via,url',
            'file_upload' => 'mimes:zip'
        ]);

        if (!File::exists('resources/views/layouts/landing/custom/index.blade.php') && ($request->landing_integration_via == 'file_upload') && (!$request->file('file_upload'))) {
            $validator->getMessageBag()->add('file_upload', translate('messages.zip_file_is_required'));
        }

        if ($validator->errors()->count() > 0) {
            $error = Helpers::error_processor($validator);
            return response()->json(['status' => 'error', 'message' => $error[0]['message']]);
        }

        DB::table('business_settings')->updateOrInsert(['key' => 'landing_integration_type'], [
            'value' => $request['landing_integration_via']
        ]);
        $status = 'success';
        $message = translate('updated_successfully!');

        if ($request->landing_integration_via == 'file_upload') {

            $file = $request->file('file_upload');
            if ($file) {

                $filename = $file->getClientOriginalName();
                $tempPath = $file->storeAs('temp', $filename);
                $zip = new \ZipArchive();
                if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {
                    // Extract the contents to a directory
                    $extractPath = base_path('resources/views/layouts/landing/custom');
                    $zip->extractTo($extractPath);
                    $zip->close();
                    // dd(File::exists($extractPath.'/index.blade.php'));
                    if (File::exists($extractPath . '/index.blade.php')) {
                        Toastr::success(translate('file_upload_successfully!'));
                        $status = 'success';
                        $message = translate('file_upload_successfully!');
                    } else {
                        File::deleteDirectory($extractPath);
                        $status = 'error';
                        $message = translate('invalid_file!');
                    }
                } else {
                    $status = 'error';
                    $message = translate('file_upload_fail!');
                }

                Storage::delete($tempPath);
            }
        }

        if ($request->landing_integration_via == 'url') {
            DB::table('business_settings')->updateOrInsert(['key' => 'landing_page_custom_url'], [
                'value' => $request['redirect_url']
            ]);

            $status = 'success';
            $message = translate('url_saved_successfully!');
        }

        return response()->json([
            'status' => $status,
            'message' => $message
        ]);
    }

    public function delete_custom_landing_page()
    {
        $filePath = 'resources/views/layouts/landing/custom/index.blade.php';

        if (File::exists($filePath)) {
            File::delete($filePath);
            Toastr::success(translate('messages.File_deleted_successfully'));
            return back();
        } else {
            Toastr::error(translate('messages.File_not_found'));
            return back();
        }
    }


    public static function product_approval_all()
    {
        $temp_data = TempProduct::where('is_rejected', 0)->get();

        foreach ($temp_data as $data) {
            $item = Item::withoutGlobalScope('translate')->with('translations')->findOrfail($data->item_id);

            $item->name = $data->name;
            $item->description =  $data->description;
            $item->image = $data->image;
            $item->images = $data->images;

            $item->store_id = $data->store_id;
            $item->module_id = $data->module_id;
            $item->unit_id = $data->unit_id;

            $item->category_id = $data->category_id;
            $item->category_ids = $data->category_ids;

            $item->choice_options = $data->choice_options;
            $item->food_variations = $data->food_variations;
            $item->variations = $data->variations;
            $item->add_ons = $data->add_ons;
            $item->attributes = $data->attributes;

            $item->price = $data->price;
            $item->discount = $data->discount;
            $item->discount_type = $data->discount_type;

            $item->available_time_starts = $data->available_time_starts;
            $item->available_time_ends = $data->available_time_ends;
            $item->maximum_cart_quantity = $data->maximum_cart_quantity;
            $item->veg = $data->veg;

            $item->organic = $data->organic;
            $item->stock =  $data->stock;
            $item->is_approved = 1;

            $item->save();
            $item->tags()->sync(json_decode($data->tag_ids));
            if ($item->module->module_type == 'pharmacy') {
                DB::table('pharmacy_item_details')
                    ->updateOrInsert(
                        ['item_id' => $item->id],
                        [
                            'common_condition_id' => $data->condition_id,
                            'is_basic' => $data->basic ?? 0,
                        ]
                    );
            }
            $item?->translations()?->delete();
            Translation::where('translationable_type', 'App\Models\TempProduct')->where('translationable_id', $data->id)->update([
                'translationable_type' => 'App\Models\Item',
                'translationable_id' => $item->id
            ]);

            $data->delete();
        }

        return true;
    }
}
