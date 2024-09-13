<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Serviceman;
use Illuminate\Support\Facades\Hash;
use App\Traits\FileManagerTrait;
use Illuminate\Support\Facades\Config;
use App\Models\Store;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Storage;
use App\Models\Order;

class ServicemanController extends Controller
{
    use FileManagerTrait;

    public function index(Request $request)
    {
        return $this->getListView($request);
    }


    private function getListView(Request $request)
    {
        $servicemen = Serviceman::where('module_id', Config::get('module.current_module_id'))->paginate(10);
        return view('admin-views.serviceman.list', compact('servicemen'));
    }

    public function create()
    {
        return view('admin-views.serviceman.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'f_name' => 'required|string|max:255',
            'l_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:servicemen',
            'store_id' => 'required|exists:stores,id',
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'identity_type' => 'required|string|max:255',
            'identity_number' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpg,png,jpeg,gif,bmp,tif,tiff|max:2048',
            'identity_image' => 'required|array',
            'identity_image.*' => 'image|mimes:jpg,png,jpeg,gif,bmp,tif,tiff|max:2048',
        ]);

        // Handle file upload for the image
        if ($request->hasFile('image')) {

            $imagePath = $this->upload('service-man/', 'png', $request->file('image'));
        }
        // Handle multiple identity images
        $identityImages = [];
        if ($request->hasFile('identity_image')) {
            foreach ($request->file('identity_image') as $file) {
                $identityImages[] = $file->store('service-man-identification', 'public');
            }
        }

        $store = Store::find($request->store_id);
        $module_id = $store->module_id;
        // dd($module_id);

        // Insert data into the servicemen table
        $serviceman = Serviceman::create([

            'first_name' => $request->f_name,
            'last_name' => $request->l_name,
            'email' => $request->email,
            'store_id' => $request->store_id,
            'phone_number' => $request->phone,
            'password' => Hash::make($request->password),
            'document_type' => $request->identity_type,
            'identity_number' => $request->identity_number,
            'serviceman_image' => $imagePath ?? null,
            'identification_image' => json_encode($identityImages),
            'module_type' => 'services',
            'status' => '1',
            'module_id' => $module_id
        ]);

        return redirect()->back()->with('success', 'Serviceman created successfully!');
    }

    public function delete(Request $request)
    {
        $serviceman = Serviceman::find($request->id);
        if ($serviceman) {
            // Delete the serviceman
            $serviceman->delete();

            // Redirect back with a success message
            return redirect()->back()->with('success', 'Serviceman deleted successfully!');
        }

        // Redirect back with an error message if the serviceman was not found
        return redirect()->back()->with('error', 'Serviceman not found!');
    }


    public function edit($id)
    {
        $servicemen = Serviceman::find($id);
        // dd($servicemen);
        return view('admin-views.serviceman.edit', compact('servicemen'));
    }

    public function updateServiceman(Request $request, $id)
    {

        $request->validate([
            'f_name' => 'required|string|max:255',
            'l_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'phone' => 'required|string|max:15',
            'identity_number' => 'required|string|max:255',
            'identity_type' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'identity_image' => 'nullable|array',
            'identity_image.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle profile image upload
        if ($request->hasFile('image')) {
            $imageName = $this->updateAndUpload('service-man/', $request->file('image'), 'png');
        } else {
            $imageName = $request->input('image');
        }


        if ($request->hasFile('identity_image')) {
            $imgKeeper = [];
            foreach ($request->file('identity_image') as $img) {
                $identityImage = $this->upload('service-man-identification/', 'png', $img);
                $imgKeeper[] = $identityImage;
            }
            $identityImage = json_encode($imgKeeper);
        } else {
            $identityImage = $request->input('identity_image');
        }



        $serviceman = Serviceman::find($id);
        $serviceman->first_name = $request->f_name;
        $serviceman->last_name = $request->l_name;
        $serviceman->email = $request->email;
        $serviceman->phone_number = $request->phone;
        $serviceman->identity_number = $request->identity_number;
        $serviceman->document_type = $request->identity_type;
        $serviceman->identification_image =  isset($identityImage) ? $identityImage : $serviceman->identification_image;



        $serviceman->serviceman_image = isset($imageName) ? $imageName : $serviceman->serviceman_image;
        if ($request->filled('password')) {
            $serviceman->password = bcrypt($request->password);
        }
        // $serviceman->status = in_array($request->input('application_status'), ['pending', 'denied']) ? 1 : $request->status;

        $serviceman->save();

        return redirect()->route('admin.service.list');
    }


    public function preview(Request $request, int|string $id, string $tab = 'info')
    {
        $deliveryMan = Serviceman::find($id);

        if ($tab === "info") {
            $reviews = $deliveryMan->reviews;
            $averageRating = $reviews->avg('rating');
            return view('admin-views.serviceman.view.info', compact('deliveryMan', 'reviews', 'averageRating'));
        } elseif ($tab === "transaction") {
            $date = $request->query('date');
            // dd($date);
            return view('admin-views.serviceman.view.transaction', compact('deliveryMan','date'));
        } elseif ($tab === "order_list") {
            $order_lists = Order::where('delivery_man_id', $deliveryMan->id)->paginate(config('default_pagination'));
            // dd($order_lists);
            return view('admin-views.serviceman.view.order_list', compact('deliveryMan', 'order_lists'));
        } elseif ($tab === "conversation") {

            return view('admin-views.serviceman.view.conversations');
        } elseif ($tab == 'disbursement') {
            // $key = explode(' ', $request['search']);
            // $disbursements=DisbursementDetails::where('delivery_man_id', $deliveryMan->id)
            //     ->when(isset($key), function ($q) use ($key){
            //         $q->where(function ($q) use ($key) {
            //             foreach ($key as $value) {
            //                 $q->orWhere('disbursement_id', 'like', "%{$value}%")
            //                     ->orWhere('status', 'like', "%{$value}%");
            //             }
            //         });
            //     })
            //     ->latest()->paginate(config('default_pagination'));

                // return view('admin-views.serviceman.view.disbursement',compact('deliveryMan','disbursements'));
            return view('admin-views.delivery-man.view.disbursement');
        }
        elseif($tab == 'disbursement'){

        }

    }
}
