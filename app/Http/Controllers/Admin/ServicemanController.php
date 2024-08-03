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
        return ;
    }

}
